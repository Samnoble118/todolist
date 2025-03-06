<?php
session_start(); 

$tasks_file = "tasks.json";

// Load tasks only once and store them in session
if (!isset($_SESSION['tasks'])) {
    $_SESSION['tasks'] = file_exists($tasks_file) ? json_decode(file_get_contents($tasks_file), true) : [];
}

$tasks = &$_SESSION['tasks'];

// Handle a new task submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task'])) {
    $new_task = [
        'id' => uniqid(),
        'task' => htmlspecialchars($_POST['task']),
        'date' => htmlspecialchars($_POST['date']),
        'completed' => false
    ];
    $tasks[] = $new_task;
    file_put_contents($tasks_file, json_encode($tasks, JSON_PRETTY_PRINT));
    header("Location: {$_SERVER['PHP_SELF']}");
    exit;
}

// Sanitize GET inputs
$complete_id = isset($_GET['complete']) ? htmlspecialchars(trim($_GET['complete'])) : null;
$delete_id = isset($_GET['delete']) ? htmlspecialchars(trim($_GET['delete'])) : null;

// Handle a task completion
if ($complete_id) {
    foreach ($tasks as &$task) {
        if ($task['id'] === $complete_id) {
            $task['completed'] = !$task['completed'];
        }
    }
    file_put_contents($tasks_file, json_encode($tasks, JSON_PRETTY_PRINT));
    header("Location: {$_SERVER['PHP_SELF']}");
    exit;
}

// Handle a task deletion
if ($delete_id) {
    $tasks = array_filter($tasks, fn($task) => $task['id'] !== $delete_id);
    $_SESSION['tasks'] = array_values($tasks); 
    file_put_contents($tasks_file, json_encode($_SESSION['tasks'], JSON_PRETTY_PRINT));
    header("Location: {$_SERVER['PHP_SELF']}");
    exit;
}

// Track "due today" alerts
$_SESSION['due_today_alerts'] = [];

foreach ($tasks as $task) {
    if (!empty($task['date']) && strtotime($task['date']) === strtotime(date('Y-m-d'))) {
        $_SESSION['due_today_alerts'][] = "Reminder: Task '{$task['task']}' is due today!";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task List</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const alertContainer = document.getElementById('alerts');
            <?php if (!empty($_SESSION['due_today_alerts'])): ?>
                <?php foreach ($_SESSION['due_today_alerts'] as $alert): ?>
                    // Create and display the alert on the page
                    const alertDiv = document.createElement('div');
                    alertDiv.classList.add('alert');
                    alertDiv.textContent = "<?= addslashes($alert) ?>";
                    alertContainer.appendChild(alertDiv);
                <?php endforeach; ?>
                <?php unset($_SESSION['due_today_alerts']); ?>
            <?php endif; ?>
        });
    </script>
</head>

<body>
    <h1>My Task List</h1>
    <form method="POST">
        <input type="text" name="task" required placeholder="Enter a new task">
        <input type="date" name="date" required placeholder="Enter a due date">
        <button type="submit">Add Task</button>
    </form>

    <!-- Alerts will be displayed here -->
    <div id="alerts"></div>

    <?php if (empty($tasks)): ?>
        <p>No tasks yet! Add one above.</p>
    <?php else: ?>
        <ul>
            <?php 
            usort($tasks, function($a, $b) {
                $dateA = new DateTime($a['date'] ?? '9999-12-31');
                $dateB = new DateTime($b['date'] ?? '9999-12-31');
                return $dateA <=> $dateB;
            });
            ?>
            <?php foreach ($tasks as $task): ?>
                <?php 
                $overdue = !empty($task['date']) && strtotime($task['date']) < strtotime(date('Y-m-d'));
                $dueToday = !empty($task['date']) && strtotime($task['date']) === strtotime(date('Y-m-d'));
                ?>
                <li class="<?= $task['completed'] ? 'completed' : ($overdue ? 'overdue' : ''); ?>">
                    <?= $overdue ? "<span class='overdue'>" . htmlspecialchars($task['task']) . "</span>" : htmlspecialchars($task['task']) ?>
                    <span><?= htmlspecialchars($task['date']) ?> <?= $dueToday ? "⚠️ Due Today" : "" ?></span>
                    <a href="?complete=<?= $task['id'] ?>" class="button">Complete</a>
                    <a href="?delete=<?= $task['id'] ?>" class="button delete">Delete</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
