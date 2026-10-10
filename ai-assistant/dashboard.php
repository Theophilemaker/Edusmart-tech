<?php
require_once '../includes/security.php';
// Assuming a role 'ai_assistant' – you can adjust
requireAnyRole(['super_admin', 'admin']); // Only admins can access for now

$db = getDB();

// Sample data for AI insights
$total_students = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$avg_progress = $db->query("SELECT AVG(progress_percentage) FROM enrollments WHERE status='active'")->fetchColumn() ?: 0;
$at_risk_students = $db->query("SELECT COUNT(*) FROM enrollments WHERE progress_percentage < 30 AND status='active'")->fetchColumn();
$popular_courses = $db->query("SELECT course_name, COUNT(*) as cnt FROM enrollments e JOIN courses c ON e.course_id=c.course_id GROUP BY c.course_id ORDER BY cnt DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4 text-white">
                        <img src="../assets/images/logo-ai.png" alt="AI" class="img-fluid" style="max-width: 120px;">
                        <h5 class="mt-2">AI Assistant</h5>
                        <span class="badge bg-purple">Artificial Intelligence</span>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="fas fa-brain me-2"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="analytics.php"><i class="fas fa-chart-pie me-2"></i> Analytics</a></li>
                        <li class="nav-item"><a class="nav-link" href="recommendations.php"><i class="fas fa-thumbs-up me-2"></i> Recommendations</a></li>
                        <li class="nav-item"><a class="nav-link" href="chatbot.php"><i class="fas fa-comment-dots me-2"></i> Chatbot</a></li>
                        <li class="nav-item"><a class="nav-link" href="predictions.php"><i class="fas fa-chart-line me-2"></i> Predictions</a></li>
                        <li class="nav-item"><a class="nav-link" href="automation.php"><i class="fas fa-robot me-2"></i> Automation</a></li>
                        <li class="nav-item mt-4"><a class="nav-link text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-robot me-2"></i>AI Assistant Dashboard</h1>
                    <div class="btn-toolbar">
                        <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#chatModal"><i class="fas fa-comment"></i> Open Chat</button>
                        <button class="btn btn-outline-success"><i class="fas fa-sync-alt"></i> Refresh Insights</button>
                    </div>
                </div>

                <!-- AI Insights Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card bg-dark-gradient text-white">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-uppercase small">Total Students</div>
                                        <div class="h2 mb-0"><?php echo $total_students; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-users fa-3x icon"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card bg-info-gradient text-white">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-uppercase small">Avg Progress</div>
                                        <div class="h2 mb-0"><?php echo round($avg_progress); ?>%</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-chart-line fa-3x icon"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card bg-warning-gradient text-white">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-uppercase small">At Risk</div>
                                        <div class="h2 mb-0"><?php echo $at_risk_students; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-exclamation-triangle fa-3x icon"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card bg-success-gradient text-white">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-uppercase small">AI Models</div>
                                        <div class="h2 mb-0">5</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-microchip fa-3x icon"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Chatbot Section -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-comment-dots me-2 text-primary"></i>AI Assistant Chat</h5>
                            </div>
                            <div class="card-body chat-container" style="height: 300px; overflow-y: auto;" id="chatMessages">
                                <div class="chat-message bot">Hello! I'm your AI assistant. How can I help you today?</div>
                                <!-- Messages will appear here -->
                            </div>
                            <div class="card-footer bg-white">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="chatInput" placeholder="Ask me anything...">
                                    <button class="btn btn-primary" id="sendChat"><i class="fas fa-paper-plane"></i></button>
                                </div>
                                <div class="typing-indicator d-none" id="typingIndicator">
                                    <span></span><span></span><span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-lightbulb me-2 text-warning"></i>AI Recommendations</h5>
                            </div>
                            <div class="list-group list-group-flush">
                                <div class="list-group-item">📚 <strong>Course Recommendation:</strong> 15 students might benefit from "Advanced Math" based on their progress.</div>
                                <div class="list-group-item">⚠️ <strong>At-risk Alert:</strong> 3 students haven't logged in for 2 weeks. Send reminders?</div>
                                <div class="list-group-item">📊 <strong>Performance Insight:</strong> Average exam scores dropped 5% this month.</div>
                                <div class="list-group-item">🎓 <strong>Certificate Eligibility:</strong> 8 students are eligible for certificates.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Popular Courses -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-star me-2 text-warning"></i>Most Popular Courses</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr><th>Course</th><th>Enrollments</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($popular_courses as $pc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pc['course_name']); ?></td>
                                    <td><?php echo $pc['cnt']; ?></td>
                                    <td><a href="#" class="btn btn-sm btn-outline-primary">Analyze</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Chat Modal (optional) -->
    <div class="modal fade" id="chatModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">AI Assistant Chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="height: 400px; overflow-y: auto;" id="modalChatMessages">
                    <div class="chat-message bot">Hello! How can I assist?</div>
                </div>
                <div class="modal-footer">
                    <input type="text" class="form-control" id="modalChatInput" placeholder="Type...">
                    <button class="btn btn-primary" id="modalSendChat">Send</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Simple chat simulation
        function sendMessage(inputId, messagesId) {
            const input = document.getElementById(inputId);
            const msg = input.value.trim();
            if (!msg) return;
            // Add user message
            const userDiv = document.createElement('div');
            userDiv.className = 'chat-message user';
            userDiv.textContent = msg;
            document.getElementById(messagesId).appendChild(userDiv);
            input.value = '';

            // Show typing
            document.getElementById('typingIndicator').classList.remove('d-none');
            // Simulate AI response after delay
            setTimeout(() => {
                document.getElementById('typingIndicator').classList.add('d-none');
                const botDiv = document.createElement('div');
                botDiv.className = 'chat-message bot';
                botDiv.textContent = "I'm processing your request. This is a simulated response.";
                document.getElementById(messagesId).appendChild(botDiv);
                // Scroll to bottom
                const container = document.getElementById(messagesId);
                container.scrollTop = container.scrollHeight;
            }, 1500);
        }

        document.getElementById('sendChat').addEventListener('click', () => sendMessage('chatInput', 'chatMessages'));
        document.getElementById('modalSendChat').addEventListener('click', () => sendMessage('modalChatInput', 'modalChatMessages'));
        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if(e.key === 'Enter') sendMessage('chatInput', 'chatMessages');
        });
    </script>
</body>
</html>