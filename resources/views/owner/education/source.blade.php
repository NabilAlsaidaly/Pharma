<fieldset class="rounded-xl border p-4" data-education-source>
    <legend>مرجع تحريري</legend>
    <label>عنوان المصدر<input name="sources[{{ $index }}][title]" required maxlength="200"
            value="{{ $source['title'] ?? '' }}"></label>
    <label>رابط HTTPS<input name="sources[{{ $index }}][url]" type="url" required maxlength="2048"
            value="{{ $source['url'] ?? '' }}" dir="ltr"></label>
    <label>ملاحظة إسناد اختيارية
        <textarea name="sources[{{ $index }}][note]" maxlength="500">{{ $source['note'] ?? '' }}</textarea>
    </label>
    <label>ترتيب المصدر<input name="sources[{{ $index }}][sort_order]" type="number" required min="0"
            max="999999999" value="{{ $source['sort_order'] ?? 0 }}"></label>
    <button type="button" data-education-source-remove>إزالة المرجع من المسودة</button>
</fieldset>
