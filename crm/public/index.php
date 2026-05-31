<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/csrf.php';
require __DIR__ . '/../app/core/auth.php';
require __DIR__ . '/../app/models/User.php';
require __DIR__ . '/../app/models/Customer.php';
require __DIR__ . '/../app/models/Contact.php';
require __DIR__ . '/../app/models/Deal.php';
require __DIR__ . '/../app/models/Activity.php';

require_auth();

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';
$id = (int) ($_GET['id'] ?? 0);
$errors = [];
$users = User::all();

function render(string $view, array $data = []): void
{
    global $page, $action;
    if (current_user_id() > 0) {
        $freshUser = User::find(current_user_id());
        if ($freshUser) {
            $_SESSION['user']['name'] = $freshUser['name'];
            $_SESSION['user']['email'] = $freshUser['email'];
        }
    }
    $data['page'] = $page;
    $data['action'] = $action;
    extract($data);
    require __DIR__ . '/../app/views/layouts/header.php';
    require __DIR__ . '/../app/views/layouts/sidebar.php';
    require __DIR__ . '/../app/views/' . $view . '.php';
    require __DIR__ . '/../app/views/layouts/footer.php';
}

function delete_action(callable $delete, string $redirectTo): void
{
    verify_csrf();
    $delete();
    redirect($redirectTo);
}

try {
    if ($page === 'dashboard') {
        $stats = [
            'customers' => (int) db()->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
            'open_deals' => (int) db()->query("SELECT COUNT(*) FROM deals WHERE deal_stage NOT IN ('Won','Lost')")->fetchColumn(),
            'pipeline' => (float) db()->query("SELECT COALESCE(SUM(estimated_amount),0) FROM deals WHERE deal_stage NOT IN ('Won','Lost')")->fetchColumn(),
            'weighted' => (float) db()->query("SELECT COALESCE(SUM(weighted_amount),0) FROM deals WHERE deal_stage NOT IN ('Won','Lost')")->fetchColumn(),
            'won_value' => (float) db()->query("SELECT COALESCE(SUM(estimated_amount),0) FROM deals WHERE deal_stage = 'Won'")->fetchColumn(),
            'lost_count' => (int) db()->query("SELECT COUNT(*) FROM deals WHERE deal_stage = 'Lost'")->fetchColumn(),
            'overdue' => Activity::overdueCount(),
        ];
        render('dashboard/index', [
            'title' => 'داشبورد فروش',
            'stats' => $stats,
            'dealsByStage' => Deal::statsByStage(),
            'customersByType' => Customer::statsByType(),
            'recentActivities' => Activity::recent(),
            'upcomingActivities' => Activity::upcoming(),
        ]);
        exit;
    }

    if ($page === 'customers') {
        if ($action === 'delete' && is_post()) {
            delete_action(fn() => Customer::delete($id), url('customers'));
        }
        if ($action === 'create') {
            $customer = [];
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['customer_code' => 'کد مشتری', 'customer_name' => 'نام مشتری']);
                if (!$errors) {
                    $newId = Customer::create($_POST);
                    redirect(url('customers', ['action' => 'show', 'id' => $newId]));
                }
                $customer = $_POST;
            }
            render('customers/create', compact('customer', 'users', 'errors') + ['title' => 'مشتری جدید']);
            exit;
        }
        if ($action === 'edit') {
            $customer = Customer::find($id);
            if (!$customer) {
                redirect(url('customers'));
            }
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['customer_code' => 'کد مشتری', 'customer_name' => 'نام مشتری']);
                if (!$errors) {
                    Customer::update($id, $_POST);
                    redirect(url('customers', ['action' => 'show', 'id' => $id]));
                }
                $customer = array_merge($customer, $_POST);
            }
            render('customers/edit', compact('customer', 'users', 'errors') + ['title' => 'ویرایش مشتری']);
            exit;
        }
        if ($action === 'show') {
            $customer = Customer::find($id);
            if (!$customer) {
                redirect(url('customers'));
            }
            render('customers/show', [
                'title' => $customer['customer_name'],
                'customer' => $customer,
                'contacts' => Contact::byCustomer($id),
                'deals' => Deal::byCustomer($id),
                'activities' => Activity::byCustomer($id),
            ]);
            exit;
        }
        render('customers/index', [
            'title' => 'مشتریان',
            'customers' => Customer::search($_GET),
            'users' => $users,
            'filters' => $_GET,
        ]);
        exit;
    }

    if ($page === 'contacts') {
        if ($action === 'delete' && is_post()) {
            $contact = Contact::find($id);
            delete_action(fn() => Contact::delete($id), url('customers', ['action' => 'show', 'id' => (int) ($contact['customer_id'] ?? 0)]));
        }
        if ($action === 'create') {
            $contact = ['customer_id' => (int) ($_GET['customer_id'] ?? 0)];
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['customer_id' => 'مشتری', 'contact_name' => 'نام مخاطب']);
                if (!$errors) {
                    Contact::create($_POST);
                    redirect(url('customers', ['action' => 'show', 'id' => (int) $_POST['customer_id']]));
                }
                $contact = $_POST;
            }
            render('contacts/create', ['title' => 'مخاطب جدید', 'contact' => $contact, 'customers' => Customer::search(), 'errors' => $errors]);
            exit;
        }
        if ($action === 'edit') {
            $contact = Contact::find($id);
            if (!$contact) {
                redirect(url('customers'));
            }
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['customer_id' => 'مشتری', 'contact_name' => 'نام مخاطب']);
                if (!$errors) {
                    Contact::update($id, $_POST);
                    redirect(url('customers', ['action' => 'show', 'id' => (int) $_POST['customer_id']]));
                }
                $contact = array_merge($contact, $_POST);
            }
            render('contacts/edit', ['title' => 'ویرایش مخاطب', 'contact' => $contact, 'customers' => Customer::search(), 'errors' => $errors]);
            exit;
        }
    }

    if ($page === 'deals') {
        if ($action === 'delete' && is_post()) {
            delete_action(fn() => Deal::delete($id), url('deals'));
        }
        if ($action === 'create') {
            $deal = ['customer_id' => (int) ($_GET['customer_id'] ?? 0), 'probability' => 20];
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['deal_name' => 'نام فرصت', 'customer_id' => 'مشتری']);
                if (!$errors) {
                    $newId = Deal::create($_POST);
                    redirect(url('deals', ['action' => 'show', 'id' => $newId]));
                }
                $deal = $_POST;
            }
            render('deals/create', ['title' => 'فرصت جدید', 'deal' => $deal, 'customers' => Customer::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        if ($action === 'edit') {
            $deal = Deal::find($id);
            if (!$deal) {
                redirect(url('deals'));
            }
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['deal_name' => 'نام فرصت', 'customer_id' => 'مشتری']);
                if (!$errors) {
                    Deal::update($id, $_POST);
                    redirect(url('deals', ['action' => 'show', 'id' => $id]));
                }
                $deal = array_merge($deal, $_POST);
            }
            render('deals/edit', ['title' => 'ویرایش فرصت', 'deal' => $deal, 'customers' => Customer::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        if ($action === 'show') {
            $deal = Deal::find($id);
            if (!$deal) {
                redirect(url('deals'));
            }
            render('deals/show', ['title' => $deal['deal_name'], 'deal' => $deal, 'activities' => Activity::byDeal($id)]);
            exit;
        }
        render('deals/index', ['title' => 'فرصت‌های فروش', 'deals' => Deal::search($_GET), 'users' => $users, 'filters' => $_GET]);
        exit;
    }

    if ($page === 'activities') {
        if ($action === 'delete' && is_post()) {
            delete_action(fn() => Activity::delete($id), url('activities'));
        }
        if ($action === 'create') {
            $activity = [
                'customer_id' => (int) ($_GET['customer_id'] ?? 0),
                'deal_id' => (int) ($_GET['deal_id'] ?? 0),
                'activity_date' => fa_date(date('Y-m-d')),
                'status' => 'Open',
            ];
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['customer_id' => 'مشتری', 'activity_date' => 'تاریخ فعالیت', 'summary' => 'خلاصه']);
                if (!$errors) {
                    Activity::create($_POST);
                    $target = !empty($_POST['deal_id']) ? url('deals', ['action' => 'show', 'id' => (int) $_POST['deal_id']]) : url('customers', ['action' => 'show', 'id' => (int) $_POST['customer_id']]);
                    redirect($target);
                }
                $activity = $_POST;
            }
            render('activities/create', ['title' => 'فعالیت جدید', 'activity' => $activity, 'customers' => Customer::search(), 'deals' => Deal::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        if ($action === 'edit') {
            $activity = Activity::find($id);
            if (!$activity) {
                redirect(url('activities'));
            }
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['customer_id' => 'مشتری', 'activity_date' => 'تاریخ فعالیت', 'summary' => 'خلاصه']);
                if (!$errors) {
                    Activity::update($id, $_POST);
                    redirect(url('activities'));
                }
                $activity = array_merge($activity, $_POST);
            }
            render('activities/edit', ['title' => 'ویرایش فعالیت', 'activity' => $activity, 'customers' => Customer::search(), 'deals' => Deal::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        render('activities/index', ['title' => 'فعالیت‌ها', 'activities' => Activity::search($_GET), 'users' => $users, 'filters' => $_GET]);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo '<div dir="rtl" style="font-family:tahoma;padding:30px">خطای پایگاه داده: ' . e($e->getMessage()) . '</div>';
    exit;
}

redirect('index.php');
