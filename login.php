<?php require_once 'configs/helper.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Beam SOHO Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="app.css">
    <script src="app.js" defer></script>
</head>
<body>

    <?php Helper::displayFlash(); ?>

    <div class="login-card">
        <div class="brand-section">
            <h1>Track Manager</h1>
            <p>Beam SOHO Test Track System</p>
        </div>

        <form action="controllers/LoginController.php" method="POST">
            <div class="form-group">
                <input type="text" name="username" id="username" class="form-control" placeholder=" " required>
                <label for="username" class="form-label">Username</label>
            </div>

            <div class="form-group">
                <input type="password" name="password" id="password" class="form-control" placeholder=" " required>
                <label for="password" class="form-label">Password</label>
            </div>

            <button type="submit" class="btn">Sign In</button>
        </form>
    </div>

</body>
</html>