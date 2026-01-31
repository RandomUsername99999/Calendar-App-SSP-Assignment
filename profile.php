<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$success = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $description = trim($_POST['description']);
    $avatar_url = trim($_POST['avatar_url']);
    
    $stmt = $pdo->prepare("UPDATE users SET description = ?, avatar_url = ? WHERE id = ?");
    if ($stmt->execute([$description, $avatar_url, $user_id])) {
        $success = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Handle Task Invitation Actions
    if ($_POST['action'] === 'accept_task') {
        $participation_id = $_POST['participation_id'];
        $stmt = $pdo->prepare("UPDATE task_participants SET status = 'accepted' WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$participation_id, $user_id])) {
            $success = "Task accepted!";
        }
    } elseif ($_POST['action'] === 'decline_task') {
        $participation_id = $_POST['participation_id'];
        $stmt = $pdo->prepare("UPDATE task_participants SET status = 'rejected' WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$participation_id, $user_id])) {
            $success = "Task declined.";
        }
    }
}

// Fetch User Details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Default Faceless Icon if no avatar
$default_avatar = "https://t4.ftcdn.net/jpg/00/64/67/27/360_F_64672736_U5kpdGs9keUll8CRQ3p3YaEv2M6qkVY5.jpg"; 
$avatar = !empty($user['avatar_url']) ? $user['avatar_url'] : $default_avatar;

// Fetch Pending Task Invitations
$stmt = $pdo->prepare("
    SELECT tp.id as participation_id, t.title, t.due_date, u.username as inviter_name
    FROM task_participants tp
    JOIN tasks t ON tp.task_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE tp.user_id = ? AND tp.status = 'pending'
");
$stmt->execute([$user_id]);
$pending_tasks = $stmt->fetchAll();

// Fetch Upcoming Tasks (My own OR Accepted Shared Tasks)
$stmt = $pdo->prepare("
    SELECT DISTINCT t.* 
    FROM tasks t
    LEFT JOIN task_participants tp ON t.id = tp.task_id
    WHERE (t.user_id = ? OR (tp.user_id = ? AND tp.status = 'accepted')) 
    AND t.due_date >= NOW() 
    ORDER BY t.due_date ASC LIMIT 5
");
$stmt->execute([$user_id, $user_id]);
$tasks = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Calendar App</title>
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
        function toggleEdit() {
            const viewMode = document.getElementById('view-mode');
            const editMode = document.getElementById('edit-mode');
            viewMode.classList.toggle('hidden');
            editMode.classList.toggle('hidden');
        }
    </script>
</head>
<body class="bg-slate-50 min-h-screen flex justify-center p-4">

    <!-- Mobile Container -->
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden relative border border-slate-100 flex flex-col h-[800px]">
        
        <!-- Header / Close Button -->
        <div class="p-4 flex justify-between items-start absolute w-full z-10">
            <button onclick="window.location.href='calendar.php'" class="bg-white/80 backdrop-blur-sm p-2 rounded-full shadow-sm hover:bg-white text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <button onclick="window.location.href='logout.php'" class="bg-white/80 backdrop-blur-sm px-3 py-2 rounded-full shadow-sm hover:bg-red-50 text-red-500 font-medium text-sm transition">
                Logout
            </button>
        </div>

        <?php if ($success): ?>
            <div class="absolute top-16 left-0 right-0 z-20 mx-4">
                <div class="bg-green-100 text-green-700 px-4 py-2 rounded shadow-sm text-center text-sm"><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto">
            
            <!-- View Mode -->
            <div id="view-mode">
                <!-- Profile Header -->
                <div class="pt-16 pb-6 px-6 bg-gradient-to-b from-slate-100 to-white flex flex-col items-center text-center border-b border-slate-100">
                    <div class="w-28 h-28 bg-slate-200 rounded-full mb-4 overflow-hidden border-4 border-white shadow-md relative group">
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="User Avatar" class="w-full h-full object-cover">
                    </div>
                    <h1 class="text-2xl font-bold text-slate-800"><?php echo htmlspecialchars($username); ?></h1>
                    <p class="text-slate-500 text-sm mt-1 px-4"><?php echo htmlspecialchars($user['description'] ?? 'No bio yet.'); ?></p>
                    
                    <button onclick="toggleEdit()" class="text-brand-600 text-sm font-medium mt-2 hover:underline">Edit Profile</button>
                    
                    <div class="flex gap-3 mt-6 w-full">
                        <button onclick="window.location.href='calendar.php'" class="flex-1 bg-brand-500 hover:bg-brand-600 text-white py-2.5 rounded-xl font-medium shadow-sm transition transform active:scale-95">
                            View Calendar
                        </button>
                        <button onclick="window.location.href='friends.php'" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white py-2.5 rounded-xl font-medium shadow-sm transition transform active:scale-95">
                            Add Friend
                        </button>
                    </div>
                </div>

                <!-- Pending Task Invitations -->
                 <?php if (count($pending_tasks) > 0): ?>
                    <div class="p-6 bg-white border-b border-slate-100">
                        <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            Task Invitations
                        </h2>
                        <div class="space-y-3">
                            <?php foreach ($pending_tasks as $invite): ?>
                                <div class="bg-orange-50 border border-orange-100 p-4 rounded-xl">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-medium text-slate-800"><?php echo htmlspecialchars($invite['title']); ?></h3>
                                            <p class="text-xs text-slate-500 mt-1">From: <?php echo htmlspecialchars($invite['inviter_name']); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo date('M d, h:i A', strtotime($invite['due_date'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex gap-2 mt-3">
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="action" value="accept_task">
                                            <input type="hidden" name="participation_id" value="<?php echo $invite['participation_id']; ?>">
                                            <button type="submit" class="w-full bg-emerald-500 text-white py-2 rounded-lg text-sm font-medium hover:bg-emerald-600 transition">Accept</button>
                                        </form>
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="action" value="decline_task">
                                            <input type="hidden" name="participation_id" value="<?php echo $invite['participation_id']; ?>">
                                            <button type="submit" class="w-full bg-white border border-slate-200 text-slate-600 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 transition">Decline</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                 <?php endif; ?>

                <!-- Upcoming Tasks -->
                <div class="p-6 bg-white">
                    <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Upcoming Tasks
                    </h2>

                    <div class="space-y-3">
                        <?php if (count($tasks) > 0): ?>
                            <?php foreach ($tasks as $task): ?>
                                <?php
                                    $taskColor = $task['color'] ?? 'blue';
                                    $dotColor = 'bg-brand-400';
                                    if ($taskColor == 'red') $dotColor = 'bg-red-400';
                                    elseif ($taskColor == 'green') $dotColor = 'bg-emerald-400';
                                    elseif ($taskColor == 'purple') $dotColor = 'bg-purple-400';
                                    elseif ($taskColor == 'orange') $dotColor = 'bg-orange-400';
                                ?>
                                <div class="bg-slate-50 border border-slate-100 p-4 rounded-xl flex items-center justify-between group hover:border-brand-200 transition-colors cursor-pointer">
                                    <div>
                                        <h3 class="font-medium text-slate-800"><?php echo htmlspecialchars($task['title']); ?></h3>
                                        <p class="text-xs text-slate-500 mt-1"><?php echo date('M d, h:i A', strtotime($task['due_date'])); ?></p>
                                    </div>
                                    <div class="h-3 w-3 rounded-full <?php echo $dotColor; ?>"></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <p class="text-slate-400 text-center py-4">No upcoming tasks.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Edit Mode -->
            <div id="edit-mode" class="hidden">
                 <div class="pt-16 pb-6 px-6 flex flex-col items-center">
                    <h2 class="text-xl font-bold text-slate-800 mb-6">Edit Profile</h2>
                    
                    <form method="POST" action="profile.php" class="w-full space-y-4">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Avatar URL</label>
                            <input type="text" name="avatar_url" value="<?php echo htmlspecialchars($user['avatar_url'] ?? ''); ?>" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-brand-500 outline-none" placeholder="https://example.com/image.jpg">
                            <p class="text-xs text-slate-400 mt-1">Leave empty for default faceless icon.</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Bio / Description</label>
                            <textarea name="description" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-brand-500 outline-none h-32 resize-none" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="toggleEdit()" class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-700 py-3 rounded-xl font-medium transition">
                                Cancel
                            </button>
                            <button type="submit" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white py-3 rounded-xl font-medium shadow-md transition">
                                Save Changes
                            </button>
                        </div>
                    </form>
                 </div>
            </div>

        </div>

    </div>

</body>
</html>
