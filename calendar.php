<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch User for Avatar
$stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$default_avatar = "https://t4.ftcdn.net/jpg/00/64/67/27/360_F_64672736_U5kpdGs9keUll8CRQ3p3YaEv2M6qkVY5.jpg";
$avatar = !empty($user['avatar_url']) ? $user['avatar_url'] : $default_avatar;


// Get current Year and Month
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');

// Navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Calendar Logic
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$numberDays = date('t', $firstDayOfMonth);
$dateComponents = getdate($firstDayOfMonth);
$dayOfWeek = $dateComponents['wday']; 

// Fetch Tasks (My Tasks + Shared Tasks)
// Distinct to avoid duplicates if user is both creator and participant (unlikely but safe)
$stmt = $pdo->prepare("
    SELECT DISTINCT t.* 
    FROM tasks t
    LEFT JOIN task_participants tp ON t.id = tp.task_id
    WHERE (t.user_id = ? OR (tp.user_id = ? AND tp.status = 'accepted')) 
    AND MONTH(t.due_date) = ? AND YEAR(t.due_date) = ?
");
$stmt->execute([$user_id, $user_id, $month, $year]);
$allTasks = $stmt->fetchAll();

$tasksByDay = [];
foreach ($allTasks as $task) {
    $day = (int)date('j', strtotime($task['due_date']));
    $tasksByDay[$day][] = $task;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Calendar App</title>
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
        
        let currentTasks = {}; // Store tasks for client-side modal

        function openDayModal(day, tasks) {
            const modal = document.getElementById('day-modal');
            const title = document.getElementById('modal-title');
            const content = document.getElementById('modal-content');
            
            title.textContent = "Tasks for <?php echo date('F'); ?> " + day;
            content.innerHTML = '';
            
            if (tasks && tasks.length > 0) {
                tasks.forEach(task => {
                    const div = document.createElement('div');
                    div.className = 'p-3 bg-slate-50 border border-slate-100 rounded-xl mb-2';
                    div.innerHTML = `
                        <h3 class="font-medium text-slate-800">${task.title}</h3>
                        <p class="text-sm text-slate-500">${task.description || ''}</p>
                        <div class="mt-2 text-xs font-semibold px-2 py-1 rounded inline-block bg-${task.color == 'purple' ? 'purple-100 text-purple-600' : 'blue-100 text-blue-600'}">
                            ${new Date(task.due_date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                        </div>
                         ${task.user_id != <?php echo $user_id; ?> ? '<span class="ml-2 text-xs bg-orange-100 text-orange-600 px-2 py-1 rounded">Shared</span>' : ''}
                    `;
                    content.appendChild(div);
                });
            } else {
                 content.innerHTML = '<p class="text-slate-400 text-center py-4">No tasks for this day.</p>';
            }

             modal.classList.remove('hidden');
        }

        function closeDayModal() {
            document.getElementById('day-modal').classList.add('hidden');
        }
    </script>
</head>
<body class="bg-slate-50 min-h-screen flex justify-center p-4">

    <!-- Mobile Container -->
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden relative border border-slate-100 flex flex-col h-[800px]">
        
        <!-- Header -->
        <div class="p-6 bg-white border-b border-slate-100 flex justify-between items-center z-10">
             <div class="flex items-center gap-3">
                 <div onclick="window.location.href='profile.php'" class="w-10 h-10 rounded-full bg-slate-200 overflow-hidden cursor-pointer border border-slate-100">
                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="User Avatar" class="w-full h-full object-cover">
                 </div>
                 <h1 class="text-xl font-bold text-slate-800">My Calendar</h1>
             </div>
             <button onclick="window.location.href='task_form.php'" class="bg-brand-500 hover:bg-brand-600 text-white p-2 rounded-full shadow-md transition transform active:scale-95">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
             </button>
        </div>

        <!-- Month Navigation -->
        <div class="px-6 py-4 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-slate-800"><?php echo date('F Y', $firstDayOfMonth); ?></h2>
            <div class="flex gap-2">
                <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="p-2 rounded-lg hover:bg-slate-100 text-slate-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </a>
                <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="p-2 rounded-lg hover:bg-slate-100 text-slate-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
            </div>
        </div>

        <!-- Days Header -->
        <div class="grid grid-cols-7 text-center px-4 mb-2">
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Sun</div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Mon</div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tue</div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Wed</div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Thu</div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Fri</div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Sat</div>
        </div>

        <!-- Calendar Grid -->
        <div class="flex-1 px-4 overflow-y-auto relative">
            <div class="grid grid-cols-7 gap-y-4 gap-x-2">
                <?php
                // Empty slots before first day
                for ($k = 0; $k < $dayOfWeek; $k++) {
                    echo '<div class="aspect-square"></div>';
                }

                $currentDay = 1;
                while ($currentDay <= $numberDays) {
                    $hasTask = isset($tasksByDay[$currentDay]);
                    $todaysTasks = $hasTask ? $tasksByDay[$currentDay] : [];
                    $jsonTasks = htmlspecialchars(json_encode($todaysTasks), ENT_QUOTES, 'UTF-8');
                    
                    $colorClass = '';
                    $specialMarker = '';
                    
                    if ($hasTask) {
                        foreach($todaysTasks as $t) {
                             $taskColor = $t['color'] ?? 'blue';
                             if ($taskColor == 'red') $colorClass = 'bg-red-400';
                             elseif ($taskColor == 'green') $colorClass = 'bg-emerald-400';
                             elseif ($taskColor == 'purple') $colorClass = 'bg-purple-400';
                             else $colorClass = 'bg-brand-500'; 
                        }
                         $specialMarker = '<span class="absolute bottom-1 w-1.5 h-1.5 '.$colorClass.' rounded-full"></span>';
                    }

                    $isToday = ($currentDay == date('j') && $month == date('m') && $year == date('Y'));
                    $todayClass = $isToday ? 'bg-slate-100 font-bold text-brand-600 border border-slate-200' : 'text-slate-700 hover:bg-slate-100';

                    echo '<div onclick="openDayModal('.$currentDay.', '.$jsonTasks.')" class="aspect-square flex items-center justify-center '.$todayClass.' text-sm font-medium rounded-full cursor-pointer transition relative group">';
                    echo $currentDay;
                    echo $specialMarker;
                    echo '</div>';

                    $currentDay++;
                    $dayOfWeek++;
                    if ($dayOfWeek == 7) {
                        $dayOfWeek = 0;
                    }
                }

                if ($dayOfWeek != 0) {
                     $remaining = 7 - $dayOfWeek;
                     for ($l = 0; $l < $remaining; $l++) {
                        echo '<div class="aspect-square"></div>';
                     }
                }
                ?>
            </div>
            
             <!-- Day Modal -->
            <div id="day-modal" class="absolute inset-0 z-50 bg-black/20 backdrop-blur-sm flex items-center justify-center p-4 hidden">
                <div class="bg-white w-full max-w-xs rounded-2xl shadow-2xl p-4 transform transition-all scale-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 id="modal-title" class="text-lg font-bold text-slate-800">Tasks</h3>
                        <button onclick="closeDayModal()" class="text-slate-400 hover:text-slate-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div id="modal-content" class="max-h-64 overflow-y-auto">
                        <!-- Tasks go here -->
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
