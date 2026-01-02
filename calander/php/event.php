<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set("session.cookie_httponly", 1); // HTTP-Only Cookies
session_start();
require_once 'utils.php';

if (!isset($_SESSION['user_id'])) {
    send_json(["success" => false, "message" => "Not logged in"], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(["success" => false, "message" => "Invalid request"], 405);
}


$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    send_json(["success" => false, "message" => "Invalid CSRF token"], 403);
}

// action what user want
$action = $_POST['action'] ?? '';




switch ($action) {
    case 'add':
        $title = trim($_POST['title'] ?? '');
        $date  = trim($_POST['event_date'] ?? '');
        $time  = trim($_POST['event_time'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $tagId = isset($_POST['tag_id']) && $_POST['tag_id'] !== '' ? (int) $_POST['tag_id'] : 1;
        $color = $_POST['color'] ?? '#007bff';

        if ($title === '' || $date === '' || $time === '') {
            send_json(["success" => false, "message" => "Missing fields"]);
        }
        $result = add_event($mysqli, $_SESSION['user_id'], $title, $date, $time, $desc, $tagId, $color);
        send_json($result);
        break;


    case 'edit':
        $id    = intval($_POST['event_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $date  = trim($_POST['event_date'] ?? '');
        $time  = trim($_POST['event_time'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#007bff';  
        if ($id === 0 || $title === '' || $date === '' || $time === '') {
            send_json(["success" => false, "message" => "Missing fields"]);
        }
        $tagId = isset($_POST['tag_id']) && $_POST['tag_id'] !== '' ? (int) $_POST['tag_id'] : 1;
        $result = edit_event($mysqli, $_SESSION['user_id'], $id, $title, $date, $time, $desc, $color, $tagId);
        send_json($result);
        break;


    case 'delete':
        $id = intval($_POST['event_id'] ?? 0);
        if ($id === 0) {
            send_json(["success" => false, "message" => "Missing event id"]);
        }
        $result = delete_event($mysqli, $_SESSION['user_id'], $id);
        send_json($result);
        break;


    case 'fetch':
        $result = fetch_events($mysqli, $_SESSION['user_id']);
        send_json($result);
        break;
    
    case 'tags':
        $result = fetch_tags($mysqli);
        send_json($result);
        break;
    
    case 'share':
        $username = trim($_POST['username'] ?? '');
        $can_edit = isset($_POST['can_edit']) ? (int)$_POST['can_edit'] : 0;
        if ($username === '') send_json(["success" => false, "message" => "Username required"]);
        $result = share_calendar($mysqli, $_SESSION['user_id'], $username, $can_edit);
        send_json($result);
        break;

    case 'shared_fetch':
        $owners = isset($_POST['owners']) ? $_POST['owners'] : '';
        $owner_ids = array_filter(array_map('intval', explode(',', $owners)));

        $shared_events = fetch_shared_events($mysqli, $_SESSION['user_id'], $owner_ids);
        send_json(["success" => true, "shared_events" => $shared_events]);
        break;

    case 'shared_list':
        $owners = fetch_shared_owners($mysqli, $_SESSION['user_id']);
        send_json(["success" => true, "owners" => $owners]);
        break;
    
    case 'group_add':
        $title = trim($_POST['title'] ?? '');
        $date = trim($_POST['event_date'] ?? '');
        $time = trim($_POST['event_time'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $participants = $_POST['participants'] ?? '';
        $color = $_POST['color'] ?? '#007bff'; 
        $tag_id = isset($_POST['tag_id']) ? (int) $_POST['tag_id'] : 1; 

        if ($title === '' || $date === '' || $time === '' || $participants === '') {
            send_json(["success" => false, "message" => "Missing fields"]);
        }

        $result = add_group_event($mysqli, $_SESSION['user_id'], $title, $date, $time, $desc, $participants, $color, $tag_id);
        send_json($result);
        break;
    
    case 'shared_with_others':
        $shared_users = fetch_shared_with_others($mysqli, $_SESSION['user_id']);
        send_json(["success" => true, "shared_users" => $shared_users]);
        break;

    case 'unshare':
        $target_user = intval($_POST['target_user_id']);
        $ok = unshare_calendar($mysqli, $_SESSION['user_id'], $target_user);
        send_json([
            "success" => $ok,
            "message" => $ok ? "Calendar unshared successfully." : "Failed to unshare."
        ]);
        break;

    default:
        send_json(["success" => false, "message" => "Unknown action"]);
}
?>