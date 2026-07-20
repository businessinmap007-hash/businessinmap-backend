<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\DisputeRuleVersion;
use App\Services\ThreadService;
use Illuminate\Http\Request;

/**
 * The rules parties agree to before they may speak in a dispute room.
 *
 * Publishing NEVER edits a past version — it adds the next one. Every
 * acceptance stores the version number it agreed to, precisely so that "they
 * accepted" can be answered with WHAT they accepted; rewriting a version in
 * place would retroactively change what people consented to, which is the one
 * thing this whole mechanism exists to prevent.
 *
 * The cost is stated plainly on the screen: publishing invalidates every
 * existing acceptance and every party must accept again before they can write.
 * That is not a side effect to be smoothed over — it is the correct behaviour,
 * and an admin who does not expect it will publish a typo fix and silence every
 * open room.
 */
final class DisputeRuleController extends Controller
{
    public function __construct(protected ThreadService $threads)
    {
    }

    public function index()
    {
        $active = DisputeRuleVersion::active();
        $versions = DisputeRuleVersion::query()->orderByDesc('version')->get();

        // What to prefill the editor with: the live rules, whether they come
        // from a published version or from the code's fallback.
        $current = $this->threads->conductCharter();

        return view('admin-v2.dispute-rules.index', compact('active', 'versions', 'current'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sections' => ['required', 'array', 'min:1'],
            // Nullable, not required: the form offers a spare empty section so a
            // third one can be added without a developer, and demanding it be
            // filled would block every ordinary publish.
            'sections.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.clauses' => ['nullable', 'string'],
        ]);

        $sections = [];

        foreach ($data['sections'] as $section) {
            // One clause per line — the editor a non-developer can actually use.
            $clauses = collect(preg_split('/\r\n|\r|\n/', (string) ($section['clauses'] ?? '')))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->values()
                ->all();

            // A section with no clauses is not a section — skipping it is what
            // lets the spare box be left blank.
            if ($clauses === []) {
                continue;
            }

            $sections[] = [
                'title' => trim((string) ($section['title'] ?? '')),
                'clauses' => $clauses,
            ];
        }

        if ($sections === []) {
            return back()->withInput()->with('error', __('لا يمكن نشر شروط بلا بنود.'));
        }

        $next = (int) max(
            (int) DisputeRuleVersion::query()->max('version'),
            ThreadService::CONDUCT_VERSION
        ) + 1;

        DisputeRuleVersion::create([
            'version' => $next,
            'title' => $data['title'],
            'sections' => $sections,
            'published_by' => (int) auth()->id(),
            'published_at' => now(),
        ]);

        return back()->with('success', __('نُشرت النسخة رقم ') . $next
            . __(' — على كل الأطراف الموافقة من جديد قبل الكتابة.'));
    }

    /** Read an older version, to see exactly what someone agreed to. */
    public function show(DisputeRuleVersion $ruleVersion)
    {
        return view('admin-v2.dispute-rules.show', ['version' => $ruleVersion]);
    }
}
