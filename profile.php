<?php
// TODO: Check if user is logged in, redirect to login.php if not
// TODO: Fetch user data from API (username, bio, location, stats, routes)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routler - Profile</title>
    <link rel="stylesheet" href="profile.css">
</head>
<body>

    <nav class="navbar">
        <a href="#" class="nav-brand">Routler</a>
        <div class="nav-links">
            <a href="front_page.html" class="nav-item">Home</a>
            <a href="create_route.html" class="nav-item">Create Route</a>
            <a href="profile.html" class="nav-item active">Profile</a>
            <a href="login.html" class="nav-item">Logout</a>
        </div>
        <div class="user">Your username</div>
    </nav>

    <div class="container">

        <div class="profile-header">
            <div class="avatar-wrapper">
                <div class="avatar">
                    <!-- TODO: Replace with profile picture from DB -->
                    <span class="avatar-initials">HC</span>
                </div>
                <button class="avatar-edit-btn">Change Photo</button>
            </div>
            <div class="profile-meta">
                <!-- TODO: Replace hardcoded values with data from API -->
                <h1 class="profile-name">Harman/Cayden</h1>
                <p class="profile-joined">Member since March 2026</p>
                <div class="profile-stats">
                    <div class="stat">
                        <span class="stat-value">12</span>
                        <span class="stat-label">Routes</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">0</span>
                        <span class="stat-label">Followers</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">0</span>
                        <span class="stat-label">Following</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">0.0</span>
                        <span class="stat-label">Rating</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-body">

            <div class="card">
                <h2>Customise Profile</h2>
                <!-- TODO: Form submission should send request to API to update profile -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <!-- TODO: Populate value from API -->
                    <input type="text" id="username" placeholder="e.g. harman_routes" value="harman_routes">
                </div>

                <div class="form-group">
                    <label for="bio">Bio</label>
                    <!-- TODO: Populate value from API -->
                    <textarea id="bio" placeholder="Tell people about yourself...">Avid walker and route explorer based in Manchester.</textarea>
                </div>

                <div class="form-group">
                    <label for="location">Location</label>
                    <!-- TODO: Populate value from API -->
                    <input type="text" id="location" placeholder="e.g. Manchester, UK" value="Manchester, UK">
                </div>

                <button class="save-btn">Save Changes</button>
            </div>

            <div class="card">
                <h2>Recent Routes</h2>
                <!-- TODO: Replace hardcoded routes with data from API -->
                <ul class="route-list">
                    <li class="route-item">
                        <div class="route-info">
                            <span class="route-title">City Centre Loop</span>
                            <span class="route-meta">5.2km · 3 stops</span>
                        </div>
                    </li>
                    <li class="route-item">
                        <div class="route-info">
                            <span class="route-title">Northern Quarter Walk</span>
                            <span class="route-meta">2.8km · 5 stops</span>
                        </div>
                    </li>
                    <li class="route-item">
                        <div class="route-info">
                            <span class="route-title">Fallowfield to Didsbury</span>
                            <span class="route-meta">4.1km · 4 stops</span>
                        </div>
                    </li>
                </ul>
            </div>

        </div>
    </div>

</body>
</html>