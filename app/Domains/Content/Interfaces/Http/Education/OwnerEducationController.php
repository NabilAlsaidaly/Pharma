<?php

namespace App\Domains\Content\Interfaces\Http\Education;

use App\Domains\Content\Application\Education\ManageEducationalArticle;
use App\Domains\Content\Application\Education\ManageEducationalCategory;
use App\Domains\Content\Domain\Education\ArticleDocument;
use App\Domains\Content\Infrastructure\Education\EducationalStore;
use App\Domains\Content\Infrastructure\Education\OwnerEducationReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OwnerEducationController
{
    public function index(Request $request, OwnerEducationReader $reader)
    {
        $filters = OwnerEducationReader::filters($request->query());

        return view('owner.education.index', ['records' => $reader->articles($filters)]);
    }

    public function edit(Request $request, OwnerEducationReader $reader, ?string $id = null)
    {
        return view('owner.education.edit', $reader->article($id, OwnerEducationReader::filters($request->query())));
    }

    public function mutate(Request $request, ManageEducationalArticle $command, ?string $id = null)
    {
        abort_if($request->query() !== [], 422);
        ArticleDocument::only($request->all(), ['_token', 'operation_id', 'expected_version', 'action', 'confirm', 'slug', 'values', 'body_document', 'products', 'concerns', 'sources', 'images', '_relations_present', 'review_acknowledged', 'health_acknowledged', 'role', 'alt_text', 'image']);
        $request->validate(['image' => 'sometimes|file']);
        $input = $request->except(['_token', 'image', '_relations_present', 'body_document']);
        if ($request->has('body_document')) {
            $json = $request->input('body_document');
            abort_unless(is_string($json) && strlen($json) <= 192 * 1024, 422);
            try {
                $input['body'] = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                abort(422, 'تعذر قراءة كتل المقال.');
            }
        }
        if ($request->has('_relations_present')) {
            foreach (['products', 'concerns', 'sources', 'images'] as $kind) {
                $input[$kind] ??= [];
            }
        }
        if (isset($input['values'])) {
            abort_unless(is_array($input['values']), 422);
            if (array_key_exists('sort_order', $input['values'])) {
                $input['values']['sort_order'] = self::integer($input['values']['sort_order']);
            }
            abort_if(isset($input['values']['is_featured']) && $input['values']['is_featured'] !== '1', 422);
            $input['values']['is_featured'] = isset($input['values']['is_featured']);
        }
        foreach (['products', 'concerns'] as $kind) {
            if (isset($input[$kind])) {
                abort_unless(is_array($input[$kind]), 422);
                $rows = [];
                foreach ($input[$kind] as $key => $row) {
                    abort_unless(is_array($row), 422);
                    ArticleDocument::only($row, ['selected', 'sort_order']);
                    if (isset($row['selected'])) {
                        abort_unless($row['selected'] === '1', 422);
                        $rows[] = ['id' => $key, 'sort_order' => self::integer($row['sort_order'] ?? null)];
                    }
                }
                $input[$kind] = $rows;
            }
        }
        if (isset($input['sources'])) {
            abort_unless(is_array($input['sources']), 422);
            $input['sources'] = array_values($input['sources']);
            foreach ($input['sources'] as &$source) {
                abort_unless(is_array($source), 422);
                $source['sort_order'] = self::integer($source['sort_order'] ?? null);
            }
            unset($source);
        }
        if (isset($input['images'])) {
            abort_unless(is_array($input['images']), 422);
            $rows = [];
            foreach ($input['images'] as $row) {
                abort_unless(is_array($row), 422);
                ArticleDocument::only($row, ['keep', 'media_id', 'role', 'alt_text']);
                if (isset($row['keep'])) {
                    abort_unless($row['keep'] === '1', 422);
                    unset($row['keep']);
                    $rows[] = $row;
                }
            }
            $input['images'] = $rows;
        }
        $target = $command->execute($id, $request->user('owner'), $input, $request->file('image'));

        return redirect()->route('owner.education.edit', ['id' => $target])->with('status', 'حُفظ إجراء المقال. تعديل المسودة لا يغيّر النسخة المنشورة.');
    }

    private static function integer(mixed $v): int
    {
        abort_unless(is_string($v) && preg_match('/^(0|[1-9][0-9]{0,8})$/D', $v), 422, 'الترتيب غير صالح.');

        return (int) $v;
    }

    public function categories(Request $request)
    {
        EducationalStore::ready();
        $f = OwnerEducationReader::filters($request->query());

        return view('owner.education.categories', ['records' => DB::table('educational_categories')->orderBy('sort_order')->orderBy('id')->paginate(25, ['*'], 'page', $f['page'] ?? 1)]);
    }

    public function categoryEdit(Request $request, ?string $id = null)
    {
        EducationalStore::ready();
        $f = OwnerEducationReader::filters($request->query());
        $data = DB::transaction(function () use ($id, $f): array {
            $record = $id ? DB::table('educational_categories')->where('id', $id)->first() : null;
            abort_if($id && ! $record, 404);
            $history = DB::table('educational_category_revisions as r')->join('management_users as o', 'o.id', '=', 'r.owner_id')->where('category_id', $id)->orderByDesc('version')->paginate(25, ['r.*', 'o.display_name'], 'page', $f['page'] ?? 1);

            return compact('record', 'history');
        });

        return view('owner.education.category', $data);
    }

    public function categoryMutate(Request $request, ManageEducationalCategory $command, ?string $id = null)
    {
        abort_if($request->query() !== [], 422);
        $input = $request->except('_token');
        if (isset($input['values'])) {
            abort_unless(is_array($input['values']), 422);
            if (array_key_exists('sort_order', $input['values'])) {
                $input['values']['sort_order'] = self::integer($input['values']['sort_order']);
            }
            abort_if(isset($input['values']['is_active']) && $input['values']['is_active'] !== '1', 422);
            $input['values']['is_active'] = isset($input['values']['is_active']);
        }
        $target = $command->execute($id, $request->user('owner'), $input);

        return redirect()->route('owner.education.category.edit', ['id' => $target])->with('status', 'حُفظت الفئة. إخفاؤها يحجب رابطها دون إخفاء المقال.');
    }

    public function media(Request $request, string $id)
    {
        EducationalStore::ready();
        abort_if($request->query() !== [], 422);
        abort_unless(DB::table('educational_revision_media')->where('media_id', $id)->exists(), 404);

        return PublicEducationController::image($id);
    }

    public function history(Request $request)
    {
        EducationalStore::ready();
        $f = OwnerEducationReader::filters($request->query());

        return view('owner.education.history', ['records' => DB::table('educational_content_events as e')->join('management_users as o', 'o.id', '=', 'e.owner_id')->orderByDesc('e.created_at')->orderByDesc('e.id')->paginate(25, ['e.*', 'o.display_name'], 'page', $f['page'] ?? 1)]);
    }
}
