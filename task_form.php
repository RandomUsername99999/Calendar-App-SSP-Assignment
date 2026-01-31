<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

/** Fetch Friends for the Selection List **/
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.avatar_url 
    FROM friendships f 
    JOIN users u ON (f.user_id_1 = u.id OR f.user_id_2 = u.id)
    WHERE (f.user_id_1 = ? OR f.user_id_2 = ?) 
    AND f.status = 'accepted' 
    AND u.id != ?
");
$stmt->execute([$user_id, $user_id, $user_id]);
$friends = $stmt->fetchAll();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $date = $_POST['date'];
    $description = trim($_POST['description']);
    $is_special = isset($_POST['is_special']) ? 1 : 0;
    $selected_friends = $_POST['friends'] ?? []; // Array of user IDs
    
    $color = $is_special ? 'purple' : 'blue'; 

    // Date Validation
    $due_timestamp = strtotime($date);
    $current_timestamp = time();
    $days_diff = ($due_timestamp - $current_timestamp) / (60 * 60 * 24);

    if (!$title || !$date) {
        $error = "Task Name and Date are required.";
    } elseif ($due_timestamp < $current_timestamp) {
        $error = "Task cannot be set in the past.";
    } elseif ($days_diff > 30) {
        $error = "Task cannot be more than 30 days in the future.";
    } else {
        try {
            // 1. Create Task
            $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, due_date, is_special, color) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$user_id, $title, $description, $date, $is_special, $color])) {
                $task_id = $pdo->lastInsertId();
                
                // 2. Add Participants
                if (!empty($selected_friends)) {
                    $insert_stmt = $pdo->prepare("INSERT INTO task_participants (task_id, user_id, status) VALUES (?, ?, 'pending')");
                    foreach ($selected_friends as $friend_id) {
                        $insert_stmt->execute([$task_id, $friend_id]);
                    }
                }

                header("Location: calendar.php");
                exit;
            } else {
                $error = "Failed to save task.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Task - Calendar App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                        }
                    }
                }
            }
        }
        function toggleFriendList() {
            const list = document.getElementById('friend-list');
            list.classList.toggle('hidden');
        }
    </script>
</head>
<body class="bg-slate-50 min-h-screen flex justify-center p-4">

    <!-- Mobile Container -->
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden relative border border-slate-100 flex flex-col h-auto min-h-[700px]">
        
        <!-- Header -->
        <div class="p-6 flex justify-between items-center border-b border-slate-100 bg-white">
            <button onclick="window.location.href='calendar.php'" class="p-2 rounded-full hover:bg-slate-100 text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <h1 class="text-xl font-bold text-slate-800">Add Task</h1>
            <div class="w-8"></div> 
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mx-6 mt-4 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Form Section -->
        <form action="task_form.php" method="POST" class="flex-1 p-6 space-y-6">
            
            <!-- Name Input -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Task Name</label>
                <input type="text" name="title" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition" placeholder="e.g., Doctor Appointment" required>
            </div>

            <!-- Date Input -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                <input type="datetime-local" name="date" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition" required>
            </div>

            <!-- Description Input -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea name="description" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition h-32 resize-none" placeholder="Add details..."></textarea>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_special" name="is_special" class="w-5 h-5 text-brand-600 rounded focus:ring-brand-500 border-gray-300">
                <label for="is_special" class="text-sm font-medium text-slate-700">Make Special (Purple)</label>
            </div>

            <!-- Add/Remove Friend Button -->
            <div>
                <button type="button" onclick="toggleFriendList()" class="w-full py-3 rounded-xl border-2 border-dashed border-emerald-500 text-emerald-600 font-medium hover:bg-emerald-50 transition flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add / Remove Friend
                </button>
                
                <!-- Friend List Dropdown -->
                <div id="friend-list" class="hidden mt-3 bg-slate-50 border border-slate-200 rounded-xl max-h-48 overflow-y-auto p-2">
                    <?php if (count($friends) > 0): ?>
                        <?php foreach ($friends as $friend): ?>
                            <label class="flex items-center gap-3 p-2 hover:bg-slate-100 rounded-lg cursor-pointer">
                                <input type="checkbox" name="friends[]" value="<?php echo $friend['id']; ?>" class="w-4 h-4 text-emerald-500 rounded border-gray-300 focus:ring-emerald-500">
                                <span class="text-sm text-slate-700"><?php echo htmlspecialchars($friend['username']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs text-slate-500 text-center py-2">No friends found. Add friends from your profile first.</p>
                    <?php endif; ?>
                </div>
            </div>


            <!-- Bottom Actions -->
            <div class="flex gap-3 pt-4">
                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white py-3.5 rounded-xl font-medium shadow-md transition transform active:scale-95">
                    Save Task
                </button>
            </div>

        </form>

    </div>

</body>
</html>
