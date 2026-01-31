<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$success = '';
$error = '';
$search_results = [];

// Handle Actions (Send Request, Accept, Decline)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_request') {
        $target_user_id = $_POST['target_user_id'];
        // Check if request already exists
        $stmt = $pdo->prepare("SELECT id FROM friendships WHERE (user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?)");
        $stmt->execute([$current_user_id, $target_user_id, $target_user_id, $current_user_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO friendships (user_id_1, user_id_2, status) VALUES (?, ?, 'pending')");
            if ($stmt->execute([$current_user_id, $target_user_id])) {
                $success = "Friend request sent!";
            } else {
                $error = "Failed to send request.";
            }
        } else {
            $error = "Request already exists or you are already friends.";
        }
    } elseif ($action === 'accept_request') {
        $friendship_id = $_POST['friendship_id'];
        $stmt = $pdo->prepare("UPDATE friendships SET status = 'accepted' WHERE id = ? AND user_id_2 = ?");
        if ($stmt->execute([$friendship_id, $current_user_id])) {
            $success = "Friend request accepted!";
        }
    } elseif ($action === 'decline_request') {
        $friendship_id = $_POST['friendship_id'];
        $stmt = $pdo->prepare("DELETE FROM friendships WHERE id = ? AND user_id_2 = ?");
        if ($stmt->execute([$friendship_id, $current_user_id])) {
            $success = "Friend request declined.";
        }
    }
}

// Handle Search
$search_query = $_GET['search'] ?? '';
if ($search_query) {
    // Find users who are NOT the current user AND NOT already in a friendship relation (pending or accepted)
    // This is a bit complex SQL, simplifying for now: Get all matching users, then filter in PHP if needed or use subquery
    $sql = "SELECT id, username, avatar_url FROM users 
            WHERE username LIKE ? 
            AND id != ? 
            AND id NOT IN (
                SELECT user_id_2 FROM friendships WHERE user_id_1 = ?
                UNION 
                SELECT user_id_1 FROM friendships WHERE user_id_2 = ?
            )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$search_query%", $current_user_id, $current_user_id, $current_user_id]);
    $search_results = $stmt->fetchAll();
}

// Fetch Pending Requests (Where current user is recipient: user_id_2)
$stmt = $pdo->prepare("
    SELECT f.id as friendship_id, u.username, u.avatar_url 
    FROM friendships f 
    JOIN users u ON f.user_id_1 = u.id 
    WHERE f.user_id_2 = ? AND f.status = 'pending'
");
$stmt->execute([$current_user_id]);
$pending_requests = $stmt->fetchAll();

// Fetch Friends (Both directions)
$stmt = $pdo->prepare("
    SELECT u.username, u.avatar_url 
    FROM friendships f 
    JOIN users u ON (f.user_id_1 = u.id OR f.user_id_2 = u.id)
    WHERE (f.user_id_1 = ? OR f.user_id_2 = ?) 
    AND f.status = 'accepted' 
    AND u.id != ?
");
$stmt->execute([$current_user_id, $current_user_id, $current_user_id]);
$friends = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends - Calendar App</title>
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
    </script>
</head>
<body class="bg-slate-50 min-h-screen flex justify-center p-4">

    <!-- Mobile Container -->
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden relative border border-slate-100 flex flex-col h-[800px]">
        
        <!-- Header -->
        <div class="p-4 flex items-center gap-3 border-b border-slate-100">
            <button onclick="window.history.back()" class="p-2 rounded-full hover:bg-slate-100 text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </button>
            <h1 class="text-xl font-bold text-slate-800">Friends</h1>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 px-4 py-2 mx-4 mt-2 rounded text-sm"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 px-4 py-2 mx-4 mt-2 rounded text-sm"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="flex-1 overflow-y-auto p-4 space-y-6">

            <!-- Search Section -->
            <section>
                <form method="GET" action="friends.php" class="flex gap-2 mb-4">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" class="flex-1 px-4 py-2 rounded-lg bg-slate-50 border border-slate-200 focus:border-brand-500 outline-none" placeholder="Search username...">
                    <button type="submit" class="bg-brand-500 text-white px-4 py-2 rounded-lg font-medium">Search</button>
                </form>

                <?php if (!empty($search_results)): ?>
                    <div class="space-y-2">
                        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Search Results</h3>
                        <?php foreach ($search_results as $user): ?>
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-slate-200 overflow-hidden">
                                        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo htmlspecialchars($user['username']); ?>" class="w-full h-full object-cover">
                                    </div>
                                    <span class="font-medium text-slate-800"><?php echo htmlspecialchars($user['username']); ?></span>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="send_request">
                                    <input type="hidden" name="target_user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="text-brand-600 font-medium text-sm hover:underline">Add</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($search_query): ?>
                    <p class="text-slate-500 text-sm text-center">No users found.</p>
                <?php endif; ?>
            </section>

            <!-- Pending Requests -->
            <?php if (!empty($pending_requests)): ?>
            <section>
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">Friend Requests</h3>
                <div class="space-y-2">
                    <?php foreach ($pending_requests as $req): ?>
                        <div class="flex items-center justify-between p-3 bg-orange-50 border border-orange-100 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-slate-200 overflow-hidden">
                                    <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo htmlspecialchars($req['username']); ?>" class="w-full h-full object-cover">
                                </div>
                                <span class="font-medium text-slate-800"><?php echo htmlspecialchars($req['username']); ?></span>
                            </div>
                            <div class="flex gap-2">
                                <form method="POST">
                                    <input type="hidden" name="action" value="accept_request">
                                    <input type="hidden" name="friendship_id" value="<?php echo $req['friendship_id']; ?>">
                                    <button type="submit" class="bg-emerald-500 text-white p-1.5 rounded-full hover:bg-emerald-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="action" value="decline_request">
                                    <input type="hidden" name="friendship_id" value="<?php echo $req['friendship_id']; ?>">
                                    <button type="submit" class="bg-red-400 text-white p-1.5 rounded-full hover:bg-red-500 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Friends List -->
            <section>
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">My Friends</h3>
                <?php if (!empty($friends)): ?>
                    <div class="space-y-2">
                        <?php foreach ($friends as $friend): ?>
                            <div class="flex items-center p-3 bg-slate-50 rounded-xl gap-3">
                                <div class="w-10 h-10 rounded-full bg-slate-200 overflow-hidden">
                                    <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo htmlspecialchars($friend['username']); ?>" class="w-full h-full object-cover">
                                </div>
                                <span class="font-medium text-slate-800"><?php echo htmlspecialchars($friend['username']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <p class="text-slate-500">No friends yet.</p>
                        <p class="text-slate-400 text-sm">Search for users to add them!</p>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </div>

</body>
</html>
