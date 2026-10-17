// A fixed document editor. Neither HTML nor the preview DOM is a saved source.
const element = (tag, text, className = '') => {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    node.className = className;
    return node;
};
const button = (text, action) => {
    const node = element('button', text);
    node.type = 'button';
    node.addEventListener('click', action);
    return node;
};
const freshRun = (text = '') => ({ text, bold: false, italic: false, href: null });
const merged = (runs) => {
    const out = [];
    for (const run of runs) {
        if (!run.text) continue;
        const last = out.at(-1);
        if (last && last.bold === run.bold && last.italic === run.italic && last.href === run.href) last.text += run.text;
        else out.push({ ...run });
    }
    return out.length ? out : [freshRun()];
};
const section = (runs, start, end) => {
    let offset = 0;
    const out = [];
    for (const run of runs) {
        const from = Math.max(0, start - offset);
        const to = Math.min(run.text.length, end - offset);
        if (to > from) out.push({ ...run, text: run.text.slice(from, to) });
        offset += run.text.length;
    }
    return out;
};
const safeHref = (value) => {
    if (!value.startsWith('https://') || /[\s\\\u0000-\u001f\u007f]/u.test(value)) return false;
    try {
        const u = new URL(value);
        return !u.username && !u.password && !u.port;
    } catch { return false; }
};

for (const form of document.querySelectorAll('[data-education-editor]')) {
    const hidden = form.querySelector('[data-education-document]');
    const host = form.querySelector('[data-education-blocks]');
    const preview = form.querySelector('[data-education-preview]');
    const status = form.querySelector('[data-education-status]');
    let doc;
    try { doc = JSON.parse(hidden.value); }
    catch {
        status.textContent = 'تعذر تحميل المستند؛ حدّثي الصفحة قبل التحرير.';
        form.querySelector('button[type="submit"], button:not([type])')?.setAttribute('disabled', 'disabled');
        continue;
    }
    let dirty = false;
    const imageNodes = [...form.querySelectorAll('[data-education-image]')];
    const images = () => imageNodes.filter(n => n.querySelector('input[type="checkbox"]').checked).map(n => ({
        id: n.dataset.mediaId, role: n.dataset.role, url: n.dataset.previewUrl,
        alt: n.querySelector('[data-image-alt]').value,
    }));
    const renderRuns = (parent, runs) => {
        for (const run of runs) {
            let node = document.createTextNode(run.text);
            for (const [flag, tag] of [['italic', 'em'], ['bold', 'strong']]) {
                if (run[flag]) { const wrap = element(tag); wrap.append(node); node = wrap; }
            }
            if (run.href && safeHref(run.href)) {
                const link = element('a'); link.href = run.href; link.rel = 'noopener noreferrer'; link.append(node); node = link;
            }
            parent.append(node);
        }
    };
    const showPreview = () => {
        preview.replaceChildren();
        for (const block of doc.blocks) {
            if (block.type === 'image') {
                const figure = element('figure');
                const image = images().find(i => i.id === block.media_id && i.role === 'inline');
                if (image) { const img = element('img'); img.src = image.url; img.alt = image.alt; figure.append(img); }
                else figure.append(element('p', 'اختاري صورة داخلية محفوظة لهذا الموضع.'));
                if (block.caption) figure.append(element('figcaption', block.caption));
                preview.append(figure);
            } else if (block.type === 'list') {
                const list = element(block.style === 'ordered' ? 'ol' : 'ul');
                for (const runs of block.items) { const li = element('li'); renderRuns(li, runs); list.append(li); }
                preview.append(list);
            } else {
                const tag = block.type === 'heading' ? (block.level === 3 ? 'h3' : 'h2') : 'p';
                const node = element(tag); renderRuns(node, block.runs); preview.append(node);
            }
        }
    };
    const sync = (changed = true) => {
        hidden.value = JSON.stringify(doc);
        dirty ||= changed;
        const total = doc.blocks.reduce((sum, b) => sum + (b.type === 'image' ? (b.caption || '').length : (b.type === 'list' ? b.items : [b.runs]).flat().reduce((s, r) => s + r.text.length, 0)), 0);
        status.textContent = `${doc.blocks.length} / 100 كتلة · ${total} / 40000 محرف تقريباً. يتحقق الخادم من الحدود النهائية.`;
        showPreview();
    };
    const move = (array, index, direction) => {
        const next = index + direction;
        if (next >= 0 && next < array.length) [array[index], array[next]] = [array[next], array[index]];
        render(); sync();
        host.children[Math.max(0, Math.min(next, host.children.length - 1))]?.querySelector('button')?.focus();
    };
    const textControl = (getRuns, setRuns, labelText) => {
        const area = element('div');
        const label = element('label', labelText);
        const textarea = element('textarea'); textarea.rows = 4; textarea.maxLength = 40000;
        textarea.value = getRuns().map(r => r.text).join('');
        label.append(textarea); area.append(label);
        textarea.addEventListener('input', () => {
            const previous = getRuns();
            const old = previous.map(r => r.text).join('');
            const next = textarea.value;
            let start = 0;
            while (start < old.length && start < next.length && old[start] === next[start]) start++;
            let suffix = 0;
            while (suffix < old.length - start && suffix < next.length - start && old[old.length - suffix - 1] === next[next.length - suffix - 1]) suffix++;
            const inherited = section(previous, Math.max(0, start - 1), start)[0] || freshRun();
            setRuns(merged([...section(previous, 0, start), { ...inherited, text: next.slice(start, next.length - suffix) }, ...section(previous, old.length - suffix, old.length)]));
            sync();
        });
        // Native textareas already paste text, and this avoids rich clipboard interpretation.
        textarea.addEventListener('paste', event => {
            if (!event.clipboardData) return;
            event.preventDefault();
            textarea.setRangeText(event.clipboardData.getData('text/plain'), textarea.selectionStart, textarea.selectionEnd, 'end');
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });
        const format = (kind, value) => {
            const start = textarea.selectionStart, end = textarea.selectionEnd;
            if (start === end) { status.textContent = 'حددي النص أولاً لتنسيقه.'; textarea.focus(); return; }
            const runs = getRuns();
            const selected = section(runs, start, end);
            const toggle = kind === 'bold' || kind === 'italic' ? !selected.every(r => r[kind]) : value;
            const updated = selected.map(r => kind === 'clear' ? freshRun(r.text) : ({ ...r, [kind]: toggle }));
            setRuns(merged([...section(runs, 0, start), ...updated, ...section(runs, end, textarea.value.length)]));
            sync(); textarea.focus(); textarea.setSelectionRange(start, end);
        };
        const tools = element('div', undefined, 'flex flex-wrap gap-2');
        tools.append(button('عريض', () => format('bold')), button('مائل', () => format('italic')), button('إزالة التنسيق', () => format('clear')));
        area.append(tools);
        const linkLabel = element('label', 'رابط HTTPS للنص المحدد (اختياري)');
        const link = element('input'); link.type = 'url'; link.maxLength = 2048; link.dir = 'ltr';
        linkLabel.append(link); area.append(linkLabel);
        area.append(button('تطبيق الرابط على النص المحدد', () => {
            if (!safeHref(link.value)) { status.textContent = 'أدخلي رابط HTTPS آمناً دون بيانات دخول أو منفذ.'; return; }
            format('href', link.value);
        }), button('إزالة رابط النص المحدد', () => format('href', null)));
        return area;
    };
    const render = () => {
        host.replaceChildren();
        doc.blocks.forEach((block, index) => {
            const box = element('fieldset', undefined, 'min-w-0 rounded-xl border p-4');
            const labels = { paragraph: 'فقرة', heading: 'عنوان فرعي', list: 'قائمة', image: 'صورة داخلية' };
            box.append(element('legend', `${index + 1} — ${labels[block.type]}`));
            const actions = element('div', undefined, 'flex flex-wrap gap-2');
            actions.append(button('لأعلى', () => move(doc.blocks, index, -1)), button('لأسفل', () => move(doc.blocks, index, 1)), button('حذف الكتلة', () => { doc.blocks.splice(index, 1); render(); sync(); }));
            box.append(actions);
            if (block.type === 'image') {
                const label = element('label', 'صورة داخلية محفوظة');
                const select = element('select'); select.append(new Option('اختاري صورة داخلية بعد رفعها وحفظها', ''));
                for (const image of images().filter(i => i.role === 'inline')) select.append(new Option(image.alt, image.id));
                select.value = block.media_id; select.addEventListener('change', () => { block.media_id = select.value; sync(); });
                label.append(select); box.append(label);
                const captionLabel = element('label', 'تعليق الصورة (اختياري)');
                const caption = element('textarea'); caption.maxLength = 500; caption.value = block.caption || '';
                caption.addEventListener('input', () => { block.caption = caption.value || null; sync(); });
                captionLabel.append(caption); box.append(captionLabel);
            } else if (block.type === 'list') {
                const label = element('label', 'نوع القائمة'); const select = element('select');
                select.append(new Option('نقطية', 'unordered'), new Option('رقمية', 'ordered')); select.value = block.style;
                select.addEventListener('change', () => { block.style = select.value; sync(); }); label.append(select); box.append(label);
                block.items.forEach((runs, itemIndex) => {
                    const row = element('fieldset', undefined, 'my-3 rounded-xl border p-3');
                    row.append(element('legend', `عنصر ${itemIndex + 1}`), textControl(() => block.items[itemIndex], value => { block.items[itemIndex] = value; }, 'نص العنصر'));
                    row.append(button('العنصر لأعلى', () => move(block.items, itemIndex, -1)), button('العنصر لأسفل', () => move(block.items, itemIndex, 1)), button('حذف العنصر', () => { if (block.items.length > 1) { block.items.splice(itemIndex, 1); render(); sync(); } }));
                    box.append(row);
                });
                box.append(button('إضافة عنصر للقائمة', () => { if (block.items.length < 50) { block.items.push([freshRun()]); render(); sync(); } else status.textContent = 'الحد الأقصى50عنصراً في القائمة.'; }));
            } else {
                if (block.type === 'heading') {
                    const label = element('label', 'مستوى العنوان'); const select = element('select');
                    select.append(new Option('عنوان فرعي h2', '2'), new Option('عنوان ثانوي h3', '3')); select.value = String(block.level);
                    select.addEventListener('change', () => { block.level = Number(select.value); sync(); }); label.append(select); box.append(label);
                }
                box.append(textControl(() => block.runs, value => { block.runs = value; }, block.type === 'heading' ? 'نص العنوان' : 'نص الفقرة'));
            }
            host.append(box);
        });
    };
    for (const add of form.querySelectorAll('[data-education-add]')) add.addEventListener('click', () => {
        if (doc.blocks.length >= 100) { status.textContent = 'الحد الأقصى100كتلة.'; return; }
        const type = add.dataset.educationAdd;
        const block = type === 'image' ? { type: 'image', media_id: '', caption: null }
            : ['ordered', 'unordered'].includes(type) ? { type: 'list', style: type, items: [[freshRun()]] }
            : { type, ...(type === 'heading' ? { level: 2 } : {}), runs: [freshRun()] };
        doc.blocks.push(block); render(); sync(); host.lastElementChild?.querySelector('textarea,select')?.focus();
    });
    let sourceIndex = form.querySelectorAll('[data-education-source]').length;
    form.querySelector('[data-education-source-add]')?.addEventListener('click', () => {
        const sources = form.querySelector('[data-education-sources]');
        if (sources.children.length >= 10) { status.textContent = 'حتى10مصادر.'; return; }
        const fragment = form.querySelector('[data-education-source-template]').content.cloneNode(true);
        for (const node of fragment.querySelectorAll('[name]')) node.name = node.name.replace('__INDEX__', String(sourceIndex));
        sourceIndex++; sources.append(fragment); dirty = true;
    });
    form.addEventListener('click', event => {
        const remove = event.target.closest('[data-education-source-remove]');
        if (remove) { remove.closest('[data-education-source]').remove(); dirty = true; }
    });
    form.addEventListener('input', () => { dirty = true; showPreview(); });
    form.addEventListener('submit', () => { hidden.value = JSON.stringify(doc); dirty = false; });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    render(); sync(false);
    for (const control of form.querySelectorAll('[data-education-add], [data-education-source-add]')) control.disabled = false;
    form.dataset.educationReady = 'true';
}

for (const form of document.querySelectorAll('[data-education-confirm]')) {
    const dialog = document.querySelector('[data-education-confirmation]');
    if (!dialog?.showModal) continue;
    let accepted = false;
    form.addEventListener('submit', event => {
        if (accepted) return;
        event.preventDefault();
        const trigger = event.submitter;
        dialog.querySelector('[data-education-confirm-label]').textContent = form.dataset.educationConfirm;
        const close = () => { dialog.close(); trigger?.focus(); };
        dialog.querySelector('[data-education-cancel]').onclick = close;
        dialog.oncancel = () => { trigger?.focus(); };
        dialog.querySelector('[data-education-accept]').onclick = () => { accepted = true; dialog.close(); form.requestSubmit(trigger); };
        dialog.showModal();
    });
}
