<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$answer = '';
$question = '';
$error = '';

// Handle natural language query
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ask'])) {
    $question = trim($_POST['question']);
    if(empty($question)) {
        $error = "Please enter a question.";
    } else {
        $answer = chatWithData($question);
        if(empty($answer)) {
            $error = "Sorry, I couldn't understand that question. Try: 'total sales today', 'low stock items', 'top selling products'";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Insights - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Additional styles for AI Insights */
        .ai-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .chat-area {
            background: #111;
            border-radius: 20px;
            border: 1px solid rgba(255, 204, 0, 0.2);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .chat-header {
            background: linear-gradient(135deg, #1a1a1a, #0a0a0a);
            padding: 20px;
            border-bottom: 1px solid rgba(255, 204, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chat-header .ai-icon {
            font-size: 32px;
            background: #ffcc00;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
        }
        .chat-header h2 {
            color: #ffcc00;
            font-size: 1.5rem;
        }
        .chat-header p {
            color: #aaa;
            font-size: 0.85rem;
            margin-top: 4px;
        }
        .chat-body {
            padding: 20px;
        }
        .query-form {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .query-form input {
            flex: 1;
            padding: 14px 18px;
            background: #222;
            border: 1px solid #333;
            border-radius: 14px;
            font-size: 16px;
            color: #fff;
            transition: all 0.3s;
        }
        .query-form input:focus {
            border-color: #ffcc00;
            outline: none;
            box-shadow: 0 0 0 2px rgba(255, 204, 0, 0.2);
        }
        .query-form button {
            padding: 14px 28px;
            background: linear-gradient(135deg, #ffcc00, #ffaa00);
            border: none;
            border-radius: 14px;
            font-weight: 600;
            color: #000;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .query-form button:hover {
            transform: translateY(-2px);
        }
        .answer-area {
            background: #0a0a0a;
            border-radius: 16px;
            padding: 20px;
            border-left: 4px solid #ffcc00;
            margin-top: 10px;
        }
        .question-display {
            color: #aaa;
            font-size: 0.9rem;
            margin-bottom: 12px;
        }
        .answer-display {
            color: #ffcc00;
            font-size: 1.2rem;
            font-weight: 500;
            line-height: 1.4;
        }
        .error-display {
            background: rgba(198, 40, 40, 0.15);
            border-left: 4px solid #c62828;
            padding: 15px;
            border-radius: 12px;
            color: #ff8888;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .feature-card {
            background: #111;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(255, 204, 0, 0.1);
            transition: all 0.3s;
        }
        .feature-card:hover {
            border-color: #ffcc00;
            transform: translateY(-4px);
        }
        .feature-icon {
            font-size: 28px;
            margin-bottom: 12px;
        }
        .feature-card h4 {
            color: #ffcc00;
            margin-bottom: 8px;
        }
        .feature-card p {
            color: #aaa;
            font-size: 0.85rem;
        }
        .example-queries {
            margin-top: 20px;
            padding: 15px;
            background: #0a0a0a;
            border-radius: 12px;
        }
        .example-queries span {
            display: inline-block;
            background: #222;
            padding: 6px 12px;
            border-radius: 20px;
            margin: 5px;
            font-size: 0.8rem;
            color: #ffcc00;
            cursor: pointer;
            border: 1px solid #333;
        }
        .example-queries span:hover {
            background: #ffcc00;
            color: #000;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar">
            <h1>AI Insights</h1>
            <div>Conversational Business Intelligence</div>
        </div>

        <div class="ai-container">
            <!-- Main Chat Area -->
            <div class="chat-area">
                <div class="chat-header">
                    <div class="ai-icon">🤖</div>
                    <div>
                        <h2>Chat with Your Data</h2>
                        <p>Ask anything about your business in plain English</p>
                    </div>
                </div>
                <div class="chat-body">
                    <form method="post" class="query-form">
                        <input type="text" name="question" id="questionInput" placeholder="e.g., How much profit did I make last week? Which items are slow-moving?" value="<?= htmlspecialchars($question) ?>" autofocus>
                        <button type="submit" name="ask">Ask AI</button>
                    </form>

                    <?php if($error): ?>
                        <div class="error-display">
                            ⚠️ <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if($answer && !$error): ?>
                        <div class="answer-area">
                            <div class="question-display">
                                📝 Your question: <strong>"<?= htmlspecialchars($question) ?>"</strong>
                            </div>
                            <div class="answer-display">
                                🧠 Alpha Vision AI:<br>
                                <?= nl2br(htmlspecialchars($answer)) ?>
                            </div>
                        </div>
                    <?php elseif(!$error && !$answer && $_SERVER['REQUEST_METHOD'] != 'POST'): ?>
                        <div class="answer-area" style="background:transparent; border-left-color:#555;">
                            <div class="question-display">✨ Ready to answer your business questions</div>
                            <div class="answer-display" style="color:#aaa;">Try: "total sales today" or "low stock items"</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Example Queries -->
            <div class="example-queries">
                <strong style="color:#ffcc00;">💡 Example questions you can ask:</strong><br>
                <span onclick="setQuestion('total sales today')">total sales today</span>
                <span onclick="setQuestion('total sales last week')">total sales last week</span>
                <span onclick="setQuestion('low stock items')">low stock items</span>
                <span onclick="setQuestion('top selling products')">top selling products</span>
                <span onclick="setQuestion('how many invoices this month')">how many invoices this month</span>
                <span onclick="setQuestion('total khata due')">total khata due</span>
            </div>

            <!-- AI Feature Cards -->
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">🎤</div>
                    <h4>Multilingual Voice AI</h4>
                    <p>Command POS in Urdu, Hindi, or English. "Add 2kg Sugar" – no keyboard needed.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📸</div>
                    <h4>Edge-AI Inventory</h4>
                    <p>Phone camera instantly counts stock. Computer vision works offline.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h4>Predictive Khata Scoring</h4>
                    <p>AI analyzes payment patterns to predict credit risk automatically.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h4>Smart Marketing Bot</h4>
                    <p>Automatic WhatsApp/SMS offers based on customer purchase history.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h4>100% Local & Private</h4>
                    <p>All AI models run on-device. Your financial data never leaves your shop.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <h4>Conversational BI</h4>
                    <p>Natural language queries instead of complex reports. Instant insights.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setQuestion(text) {
    document.getElementById('questionInput').value = text;
    document.getElementById('questionInput').focus();
}
</script>
</body>
</html>