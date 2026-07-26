<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Projects\ProjectService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only oversight of every business's project timeline. An admin can browse
 * all projects (filter by status/visibility, search a business or reference) and
 * open one to see its computed timeline, stages, camera evidence, and followers.
 * Nothing here edits a project — it is a supervision surface only.
 */
class ProjectAdminController extends Controller
{
    public function __construct(private readonly ProjectService $projects)
    {
    }

    /** GET admin/projects — all projects across businesses. */
    public function index(Request $request): View
    {
        $status = trim((string) $request->get('status', ''));
        $visibility = trim((string) $request->get('visibility', ''));
        $q = trim((string) $request->get('q', ''));

        $rows = Project::query()
            ->with('business:id,name')
            ->withCount(['tasks', 'followers'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($visibility !== '', fn ($query) => $query->where('visibility', $visibility))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('reference', 'like', "%{$q}%")
                        ->orwhereHas('business', fn ($b) => $b->where('name', 'like', "%{$q}%"));
                });
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin-v2.projects.index', [
            'rows' => $rows,
            'statuses' => Project::STATUSES,
            'visibilities' => Project::VISIBILITIES,
            'filters' => ['status' => $status, 'visibility' => $visibility, 'q' => $q],
        ]);
    }

    /** GET admin/projects/{project} — one project's timeline + evidence. */
    public function show(Project $project): View
    {
        $project->load([
            'business:id,name',
            'tasks.photos',
            'followers.user:id,name',
        ]);

        return view('admin-v2.projects.show', [
            'project' => $project,
            'timeline' => $this->projects->timeline($project),
        ]);
    }
}
