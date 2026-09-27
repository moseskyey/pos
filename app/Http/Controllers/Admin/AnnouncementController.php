<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnouncementRequest;
use App\Models\Platform\AdminActivity;
use App\Models\Platform\Announcement;
use App\Models\Platform\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index');
    }

    public function create(): View
    {
        return $this->form(new Announcement(['level' => 'info', 'is_active' => true, 'starts_at' => now()]));
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $announcement = Announcement::create($request->validated() + ['created_by' => auth('admin')->id()]);
        AdminActivity::record('announcement.created', "Posted announcement: {$announcement->title}");

        return redirect()->route('admin.announcements.index')->with('success', __('Announcement posted.'));
    }

    public function edit(Announcement $announcement): View
    {
        return $this->form($announcement);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($request->validated());
        AdminActivity::record('announcement.updated', "Updated announcement: {$announcement->title}");

        return redirect()->route('admin.announcements.index')->with('success', __('Announcement updated.'));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();
        AdminActivity::record('announcement.deleted', "Deleted announcement: {$announcement->title}");

        return redirect()->route('admin.announcements.index')->with('success', __('Announcement deleted.'));
    }

    protected function form(Announcement $announcement): View
    {
        return view('admin.announcements.form', [
            'announcement' => $announcement,
            'tenants' => Tenant::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
