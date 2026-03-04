<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\WalletNoteTemplate;
use Illuminate\Http\Request;

final class WalletNoteTemplateController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int)$perPage;
        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function keepQs(Request $request): array
    {
        return $request->only(['q','active','per_page','sort','dir']);
    }

    public function index(Request $request)
    {
        $q       = trim((string)$request->get('q',''));
        $active  = (string)$request->get('active',''); // '' | '1' | '0'
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $sortAllowed = ['id','title','is_active','sort','created_at'];
        $sort = (string)$request->get('sort','sort');
        if (!in_array($sort, $sortAllowed, true)) $sort = 'sort';

        $dir = strtolower((string)$request->get('dir','asc'));
        $dir = in_array($dir, ['asc','desc'], true) ? $dir : 'asc';

        $base = WalletNoteTemplate::query();

        if ($q !== '') {
            $base->where(function($w) use ($q){
                $w->where('title','like',"%{$q}%")
                  ->orWhere('text','like',"%{$q}%")
                  ->orWhere('id',$q);
            });
        }

        if ($active === '1') $base->where('is_active', true);
        if ($active === '0') $base->where('is_active', false);

        $items = (clone $base)
            ->orderBy($sort, $dir)
            ->orderBy('id','desc')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin-v2.wallet-notes.index', [
            'items' => $items,
            'q' => $q,
            'active' => $active,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
            'qsKeep' => $this->keepQs($request),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin-v2.wallet-notes.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required','string','max:120','unique:wallet_note_templates,title'],
            'text'  => ['required','string','max:255'],
            'sort'  => ['nullable','integer','min:0'],
            'is_active' => ['nullable','boolean'],
        ]);

        $data['sort'] = (int)($data['sort'] ?? 0);
        $data['is_active'] = (bool)($data['is_active'] ?? false);

        WalletNoteTemplate::create($data);

        return redirect()
            ->route('admin.wallet-notes.index')
            ->with('success', 'تم إنشاء الملاحظة بنجاح');
    }

    public function edit(WalletNoteTemplate $walletNote)
    {
        return view('admin-v2.wallet-notes.edit', compact('walletNote'));
    }

    public function update(Request $request, WalletNoteTemplate $walletNote)
    {
        $data = $request->validate([
            'title' => ['required','string','max:120','unique:wallet_note_templates,title,'.$walletNote->id],
            'text'  => ['required','string','max:255'],
            'sort'  => ['nullable','integer','min:0'],
            'is_active' => ['nullable','boolean'],
        ]);

        $data['sort'] = (int)($data['sort'] ?? 0);
        $data['is_active'] = (bool)($data['is_active'] ?? false);

        $walletNote->update($data);

        return redirect()
            ->route('admin.wallet-notes.index')
            ->with('success', 'تم حفظ التعديلات');
    }

    public function destroy(WalletNoteTemplate $walletNote)
    {
        $walletNote->delete();

        return redirect()
            ->route('admin.wallet-notes.index')
            ->with('success', 'تم حذف الملاحظة');
    }
}