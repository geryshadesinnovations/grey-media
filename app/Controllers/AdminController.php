<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\Analytics;
use App\Models\Category;
use App\Models\Company;
use App\Models\DownloadRequest;
use App\Models\Media;
use App\Models\Notification;
use App\Models\Section;
use App\Models\User;

final class AdminController
{
    public function dashboard(): void
    {
        $stats = Media::statsOverview();
        $recent = Database::all(
            "SELECT a.*, u.name AS user_name FROM activity_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT 30"
        );
        $live = Database::all(
            "SELECT us.*, u.name, u.username, m.title AS media_title, m.uuid AS media_uuid
             FROM user_sessions us
             JOIN users u ON u.id = us.user_id
             LEFT JOIN media m ON m.id = us.current_media_id
             WHERE us.is_active = 1 AND us.last_activity_at > (NOW() - INTERVAL 15 MINUTE)
             ORDER BY us.last_activity_at DESC LIMIT 30"
        );
        echo view('admin/dashboard', [
            'stats'         => $stats,
            'recent'        => $recent,
            'live'          => $live,
            'topViewed'     => Media::topViewed(10),
            'topDownloaded' => Media::topDownloaded(10),
        ]);
    }

    // -------- USERS --------
    public function users(): void
    {
        echo view('admin/users', [
            'users' => User::all(),
            'roles' => User::roles(),
        ]);
    }

    public function userStore(): void
    {
        Csrf::verifyOrFail();
        $username = trim((string) ($_POST['username'] ?? ''));
        if (!User::isValidUsername($username)) {
            flash('error', 'Username must be 3-64 characters, letters and numbers only.'); redirect('/admin/users');
        }
        if (User::usernameExists($username)) {
            flash('error', 'A user with this username already exists.'); redirect('/admin/users');
        }
        $id = User::create([
            'name'             => trim((string) ($_POST['name'] ?? '')),
            'username'         => $username,
            'password'         => (string) ($_POST['password'] ?? ''),
            'role_id'          => (int) ($_POST['role_id'] ?? 0),
            'can_graphics'     => !empty($_POST['can_graphics']),
            'can_events'       => !empty($_POST['can_events']),
            'can_upload'       => !empty($_POST['can_upload']),
            'can_edit'         => !empty($_POST['can_edit']),
            'can_delete'       => !empty($_POST['can_delete']),
            'can_download'     => !empty($_POST['can_download']),
            'can_manage_users' => !empty($_POST['can_manage_users']),
            'is_active'        => 1,
        ]);
        ActivityLog::record('user.create', 'user', $id);
        flash('success', 'User created.');
        redirect('/admin/users');
    }

    public function userUpdate(int $id): void
    {
        Csrf::verifyOrFail();
        if (!User::find($id)) { http_response_code(404); return; }
        $username = trim((string) ($_POST['username'] ?? ''));
        if (!User::isValidUsername($username)) {
            flash('error', 'Username must be 3-64 characters, letters and numbers only.'); redirect('/admin/users');
        }
        if (User::usernameExists($username, $id)) {
            flash('error', 'A user with this username already exists.'); redirect('/admin/users');
        }
        User::update($id, [
            'name'             => $_POST['name'] ?? null,
            'username'         => $username,
            'role_id'          => (int) ($_POST['role_id'] ?? 0),
            'can_graphics'     => !empty($_POST['can_graphics']),
            'can_events'       => !empty($_POST['can_events']),
            'can_upload'       => !empty($_POST['can_upload']),
            'can_edit'         => !empty($_POST['can_edit']),
            'can_delete'       => !empty($_POST['can_delete']),
            'can_download'     => !empty($_POST['can_download']),
            'can_manage_users' => !empty($_POST['can_manage_users']),
            'is_active'        => !empty($_POST['is_active']),
            'password'         => $_POST['password'] ?? null,
        ]);
        ActivityLog::record('user.update', 'user', $id);
        flash('success', 'User updated.');
        redirect('/admin/users');
    }

    public function userDelete(int $id): void
    {
        Csrf::verifyOrFail();
        if ($id === Auth::id()) {
            flash('error', 'You cannot deactivate your own account.');
            redirect('/admin/users');
        }
        User::delete($id);
        ActivityLog::record('user.deactivate', 'user', $id);
        flash('success', 'User deactivated.');
        redirect('/admin/users');
    }

    // -------- CATEGORIES --------
    public function categories(): void
    {
        $sections = Section::all();
        $trees = [];
        foreach ($sections as $s) $trees[$s['code']] = Category::tree((int) $s['id']);
        echo view('admin/categories', [
            'sections' => $sections,
            'trees'    => $trees,
        ]);
    }

    public function categoryStore(): void
    {
        Csrf::verifyOrFail();
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $parentId  = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        $name      = trim((string) ($_POST['name'] ?? ''));
        if ($sectionId === 0 || $name === '') {
            flash('error', 'Section and name are required.'); redirect('/admin/categories');
        }
        $id = Category::create($sectionId, $parentId, $name);
        ActivityLog::record('category.create', 'category', $id, ['name' => $name]);
        flash('success', 'Category created.');
        redirect('/admin/categories');
    }

    public function categoryDelete(int $id): void
    {
        Csrf::verifyOrFail();
        Category::delete($id);
        ActivityLog::record('category.delete', 'category', $id);
        flash('success', 'Category deleted.');
        redirect('/admin/categories');
    }

    // -------- ACTIVITY --------
    public function activity(): void
    {
        $rows = Database::all(
            "SELECT a.*, u.name AS user_name, u.username AS user_username
             FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT 200"
        );
        echo view('admin/activity', ['rows' => $rows]);
    }

    // -------- DOWNLOAD REQUESTS --------
    public function downloadRequests(): void
    {
        echo view('admin/download_requests', [
            'requests' => DownloadRequest::listForAdmin(150),
            'pending'  => DownloadRequest::pendingCount(),
        ]);
    }

    public function downloadRequestApprove(int $id): void
    {
        Csrf::verifyOrFail();
        $req = DownloadRequest::find($id);
        if (!$req) { http_response_code(404); return; }
        if ($req['status'] !== 'pending') {
            flash('error', 'This request has already been reviewed.');
            redirect('/admin/download-requests');
        }
        DownloadRequest::approve($id, (int) Auth::id(), 7);
        $m = Media::find((int) $req['media_id']);
        ActivityLog::record('download.request.approve', 'media', (int) $req['media_id'], ['request_id' => $id]);
        Notification::create(
            (int) $req['user_id'],
            'download_approved',
            'Download request approved',
            'Your request to download "' . ($m['title'] ?? 'an item') . '" was approved. Open it to download (single use).',
            $m ? url('/media/' . $m['uuid']) : null
        );
        flash('success', 'Request approved. The user can now download the file once.');
        redirect('/admin/download-requests');
    }

    public function downloadRequestReject(int $id): void
    {
        Csrf::verifyOrFail();
        $req = DownloadRequest::find($id);
        if (!$req) { http_response_code(404); return; }
        if ($req['status'] !== 'pending') {
            flash('error', 'This request has already been reviewed.');
            redirect('/admin/download-requests');
        }
        DownloadRequest::reject($id, (int) Auth::id());
        $m = Media::find((int) $req['media_id']);
        ActivityLog::record('download.request.reject', 'media', (int) $req['media_id'], ['request_id' => $id]);
        Notification::create(
            (int) $req['user_id'],
            'download_rejected',
            'Download request declined',
            'Your request to download "' . ($m['title'] ?? 'an item') . '" was declined.',
            $m ? url('/media/' . $m['uuid']) : null
        );
        flash('success', 'Request rejected.');
        redirect('/admin/download-requests');
    }

    // -------- ANALYTICS --------
    public function analytics(): void
    {
        echo view('admin/analytics', [
            'stats'           => Media::statsOverview(),
            'storageByType'   => Analytics::storageByType(),
            'viewsSeries'     => Analytics::dailySeries('view_logs', 30),
            'downloadsSeries' => Analytics::dailySeries('download_logs', 30),
            'uploadsSeries'   => Analytics::dailySeries('media', 30),
            'topViewed'       => Media::topViewed(10),
            'topDownloaded'   => Media::topDownloaded(10),
            'activeUsers'     => Analytics::activeUsers(30, 10),
            'actions'         => Analytics::actionBreakdown(30),
        ]);
    }

    /** Stream a CSV report (activity log or downloads). */
    public function analyticsExport(): void
    {
        $report = (string) ($_GET['report'] ?? 'activity');
        if (!in_array($report, ['activity', 'downloads'], true)) $report = 'activity';

        if ($report === 'downloads') {
            $rows = Analytics::downloadsForExport(20000);
            $headers = ['created_at', 'media_title', 'media_type', 'user', 'ip_address', 'bytes_sent'];
            $filename = 'downloads-report-' . date('Ymd-His') . '.csv';
        } else {
            $rows = Analytics::activityForExport(20000);
            $headers = ['created_at', 'action', 'entity_type', 'entity_id', 'user', 'ip_address'];
            $filename = 'activity-report-' . date('Ymd-His') . '.csv';
        }

        ActivityLog::record('analytics.export', null, null, ['report' => $report, 'rows' => count($rows)]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, no-store');

        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $r) {
            $line = [];
            foreach ($headers as $h) $line[] = $r[$h] ?? '';
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    // -------- COMPANIES --------
    public function companies(): void
    {
        echo view('admin/companies', [
            'companies' => Company::all(),
        ]);
    }

    public function companyStore(): void
    {
        Csrf::verifyOrFail();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Company name is required.');
            redirect('/admin/companies');
        }
        $id = Company::create($name);
        ActivityLog::record('company.create', 'company', $id, ['name' => $name]);
        flash('success', 'Company created.');
        redirect('/admin/companies');
    }

    public function companyDelete(int $id): void
    {
        Csrf::verifyOrFail();
        Company::delete($id);
        ActivityLog::record('company.delete', 'company', $id);
        flash('success', 'Company deleted.');
        redirect('/admin/companies');
    }
}
