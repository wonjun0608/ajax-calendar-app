<?php
require_once __DIR__ . '/database.php';

function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function send_json($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// register new user
function register_user($mysqli, $username, $email, $password) {
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ["success" => false, "message" => "Username already taken"];
    }
    $stmt->close();

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare("INSERT INTO users (username, password_hash, email) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $username, $hash, $email);
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        return ["success" => true, "user_id" => $id, "username" => $username];
    }
    $stmt->close();
    return ["success" => false, "message" => "Registration failed"];
}
// login user
function login_user($mysqli, $username, $password) {
    $stmt = $mysqli->prepare("SELECT user_id, password_hash FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->bind_result($user_id, $hash);
    $stmt->fetch();
    $stmt->close();

    if ($hash && password_verify($password, $hash)) {
        return ["success" => true, "user_id" => $user_id, "username" => $username];
    }
    return ["success" => false, "message" => "Invalid credentials"];
}


// add new event
function add_event($mysqli, $user_id, $title, $date, $time, $desc, $tagId = 1, $color = '#007bff') {
    $stmt = $mysqli->prepare("
        INSERT INTO events (user_id, title, event_date, event_time, description, tag_id, color)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('issssis', $user_id, $title, $date, $time, $desc, $tagId, $color);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        return ["success" => true, "message" => "Event added"];
    } else {
        return ["success" => false, "message" => "Failed to add event: " . $stmt->error];
    }
}

// edit event info
function edit_event($mysqli, $user_id, $event_id, $title, $event_date, $event_time, $description, $color = '#007bff', $tag_id = 1) {
    $stmt = $mysqli->prepare(
        "UPDATE events
         SET title=?, event_date=?, event_time=?, description=?, color=?, tag_id=?
         WHERE event_id=? AND user_id=?"
    );
    $stmt->bind_param('sssssiii', $title, $event_date, $event_time, $description, $color, $tag_id, $event_id, $user_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success
        ? ["success" => true]
        : ["success" => false, "message" => "Update failed: " . $stmt->error];
}

//delte event
function delete_event($mysqli, $user_id, $event_id) {
    $mysqli->begin_transaction();
    try {
        //check event owner first 
        $stmt = $mysqli->prepare("SELECT user_id FROM events WHERE event_id = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $stmt->bind_result($owner_id);
        $stmt->fetch();
        $stmt->close();

        if (!$owner_id) {
            throw new Exception("Event not found");
        }

        // if its owner, delete from events and related tables
        if ($owner_id == $user_id) {
            // remove from group
            $stmt = $mysqli->prepare("DELETE FROM group_events WHERE event_id = ?");
            $stmt->bind_param('i', $event_id);
            $stmt->execute();
            $stmt->close();

            // events delte
            $stmt = $mysqli->prepare("DELETE FROM events WHERE event_id = ?");
            $stmt->bind_param('i', $event_id);
            $stmt->execute();
            $stmt->close();
        } 
        else {
             // if participant, only delete from group events.
            $stmt = $mysqli->prepare("DELETE FROM group_events WHERE event_id = ? AND participant_id = ?");
            $stmt->bind_param('ii', $event_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        $mysqli->commit();
        return ["success" => true, "message" => "Event deleted successfully."];
    } catch (Exception $e) {
        $mysqli->rollback();
        return ["success" => false, "message" => "Error: ".$e->getMessage()];
    }
}

// get events for user
function fetch_events($mysqli, $user_id) {
 $stmt = $mysqli->prepare("
        SELECT 
            e.event_id,
            e.title,
            e.event_date,
            e.event_time,
            e.description,
            e.color,
            e.tag_id,
            CASE 
                WHEN EXISTS (
                    SELECT 1 
                    FROM group_events ge 
                    WHERE ge.event_id = e.event_id
                ) THEN 1 
                ELSE 0 
            END AS is_group,
            u.username
        FROM events e
        LEFT JOIN users u ON e.user_id = u.user_id
        WHERE 
            (
                e.user_id = ?
                AND e.event_id NOT IN (
                    SELECT event_id FROM group_events WHERE participant_id = ?
                )
            )
            OR EXISTS (
                SELECT 1 
                FROM group_events ge2 
                WHERE ge2.event_id = e.event_id 
                AND ge2.participant_id = ?
            )
        ORDER BY e.event_date, e.event_time
    ");

    $stmt->bind_param('iii', $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();

    return ["success" => true, "events" => $events];
}


// tag functions
function fetch_tags($mysqli) {
    $result = $mysqli->query("SELECT tag_id, tag_name, color FROM event_tags ORDER BY tag_id ASC");
    $tags = [];
    while ($row = $result->fetch_assoc()) {
        $tags[] = $row;
    }
    return ["success" => true, "tags" => $tags];
}

function add_tag_to_event($mysqli, $event_id, $tag_id) {
    $stmt = $mysqli->prepare("INSERT INTO event_tag_map (event_id, tag_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $event_id, $tag_id);
    $stmt->execute();
    $stmt->close();
}

function fetch_event_tags($mysqli, $event_id) {
    $stmt = $mysqli->prepare("
        SELECT t.tag_id, t.tag_name, t.color
        FROM event_tags t
        JOIN event_tag_map m ON t.tag_id = m.tag_id
        WHERE m.event_id = ?
    ");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tags = [];
    while ($row = $result->fetch_assoc()) {
        $tags[] = $row;
    }
    $stmt->close();
    return $tags;
}

function share_calendar($mysqli, $owner_id, $shared_with_username, $can_edit = false) {
    // Get shared user's ID
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param('s', $shared_with_username);
    $stmt->execute();
    $stmt->bind_result($shared_with_id);
    $stmt->fetch();
    $stmt->close();

    if (!$shared_with_id) {
        return ["success" => false, "message" => "User not found"];
    }

    // Prevent self-sharing
    if ($shared_with_id == $owner_id) {
        return ["success" => false, "message" => "You cannot share with yourself"];
    }

    // Insert or update permission
    $stmt = $mysqli->prepare("
        INSERT INTO shared_calendars (owner_id, shared_with_id, can_edit)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE can_edit = VALUES(can_edit)
    ");
    $stmt->bind_param('iii', $owner_id, $shared_with_id, $can_edit);
    $success = $stmt->execute();
    $stmt->close();

    return $success
        ? ["success" => true, "message" => "Calendar shared with $shared_with_username"]
        : ["success" => false, "message" => "Failed to share calendar"];
}

function fetch_shared_events($mysqli, $user_id, $owner_ids = []) {
    $query = "
        SELECT 
            e.event_id,
            e.title,
            e.event_date,
            e.event_time,
            e.description,
            e.color,
            e.tag_id,
            u.username,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM group_events ge WHERE ge.event_id = e.event_id
                ) THEN 1
                ELSE 0
            END AS is_group
        FROM events e
        JOIN shared_calendars s ON e.user_id = s.owner_id
        JOIN users u ON s.owner_id = u.user_id
        WHERE s.shared_with_id = ?
    ";

    if (!empty($owner_ids)) {
        $placeholders = implode(',', array_fill(0, count($owner_ids), '?'));
        $query .= " AND s.owner_id IN ($placeholders)";
    }

    $stmt = $mysqli->prepare($query);
    $types = str_repeat('i', 1 + count($owner_ids));
    $params = array_merge([$user_id], $owner_ids);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();

    return $events;
}

// make group event
function add_group_event($mysqli, $creator_id, $title, $date, $time, $desc, $participants_csv, $color, $tag_id = 1) { 
    $mysqli->begin_transaction();
    try {
        // save creator event
        $stmt = $mysqli->prepare("
            INSERT INTO events (user_id, title, event_date, event_time, description, color, tag_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        "); 
        $stmt->bind_param('isssssi', $creator_id, $title, $date, $time, $desc, $color, $tag_id); 
        $stmt->execute();
        $event_id = $stmt->insert_id;
        $stmt->close();

        // save participants
        $participants = explode(',', $participants_csv);
        $stmt_user = $mysqli->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt_insert = $mysqli->prepare("INSERT INTO group_events (event_id, participant_id) VALUES (?, ?)");

        foreach ($participants as $uname) {
            $uname = trim($uname);
            if ($uname === '') continue;

            $stmt_user->bind_param('s', $uname);
            $stmt_user->execute();
            $res = $stmt_user->get_result();

            if ($row = $res->fetch_assoc()) {
                $participant_id = $row['user_id'];
                $stmt_insert->bind_param('ii', $event_id, $participant_id);
                $stmt_insert->execute();
            } else {
                error_log("Username not found: $uname");
            }
        }

        $stmt_user->close();
        $stmt_insert->close();
        $mysqli->commit();

        return ["success" => true, "message" => "Group event added with correct tag and color."];
    } catch (Exception $e) {
        $mysqli->rollback();
        return ["success" => false, "message" => "Error: ".$e->getMessage()];
    }
}



function fetch_group_events($mysqli, $user_id) {
    $stmt = $mysqli->prepare("
        SELECT e.*, u.username
        FROM events e
        JOIN group_events g ON e.event_id = g.event_id
        JOIN users u ON e.user_id = u.user_id
        WHERE g.participant_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return ["success" => true, "events" => $events];
}

function fetch_shared_owners($mysqli, $user_id) {
    $stmt = $mysqli->prepare("
        SELECT u.user_id, u.username, s.can_edit
        FROM shared_calendars s
        JOIN users u ON s.owner_id = u.user_id
        WHERE s.shared_with_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $owners = [];
    while ($row = $result->fetch_assoc()) {
        $owners[] = $row;
    }
    $stmt->close();
    return $owners;
}


function fetch_shared_with_others($mysqli, $owner_id) {
    $stmt = $mysqli->prepare("
        SELECT u.user_id, u.username, s.can_edit
        FROM shared_calendars s
        JOIN users u ON s.shared_with_id = u.user_id
        WHERE s.owner_id = ?
    ");
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
    return $users;
}

function unshare_calendar($mysqli, $owner_id, $target_user_id) {
    $stmt = $mysqli->prepare("DELETE FROM shared_calendars WHERE owner_id = ? AND shared_with_id = ?");
    $stmt->bind_param('ii', $owner_id, $target_user_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

?>