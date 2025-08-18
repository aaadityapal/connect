<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Minimal Sidebar</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: Arial, sans-serif;
    }

    body {
      display: flex;
      min-height: 100vh;
      background: #f9f9f9;
    }

    /* Sidebar */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 220px;
      background: #111;
      color: white;
      transition: width 0.3s ease;
      overflow-x: hidden;
      padding-top: 60px;
    }

    .sidebar.collapsed {
      width: 60px;
    }

    .sidebar a {
      display: block;
      padding: 12px 20px;
      text-decoration: none;
      color: white;
      transition: 0.2s;
      white-space: nowrap;
    }

    .sidebar a:hover {
      background: #333;
    }

    .sidebar.collapsed a span {
      display: none;
    }

    /* Toggle Button */
    .toggle-btn {
      position: fixed;
      top: 15px;
      left: 15px;
      font-size: 22px;
      cursor: pointer;
      color: #111;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 5px;
      padding: 6px 10px;
      z-index: 1000;
    }

    /* Content */
    .content {
      margin-left: 220px;
      padding: 20px;
      width: 100%;
      transition: margin-left 0.3s ease;
    }

    .collapsed ~ .content {
      margin-left: 60px;
    }

    /* Mobile Styles */
    @media (max-width: 768px) {
      .sidebar {
        left: -220px;
      }

      .sidebar.active {
        left: 0;
      }

      .content {
        margin-left: 0;
      }

      .collapsed ~ .content {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>

  <div class="toggle-btn" id="toggleBtn">☰</div>

  <div class="sidebar" id="sidebar">
    <a href="#"><span>🏠</span> <span>Home</span></a>
    <a href="#"><span>📄</span> <span>Documents</span></a>
    <a href="#"><span>📧</span> <span>Messages</span></a>
    <a href="#"><span>⚙️</span> <span>Settings</span></a>
  </div>

  <div class="content">
    <h1>Responsive Minimal Sidebar</h1>
    <p>This is the main content area. Resize the screen to see responsiveness.</p>
  </div>

  <script>
    const toggleBtn = document.getElementById("toggleBtn");
    const sidebar = document.getElementById("sidebar");

    toggleBtn.addEventListener("click", () => {
      if (window.innerWidth > 768) {
        sidebar.classList.toggle("collapsed");
      } else {
        sidebar.classList.toggle("active");
      }
    });
  </script>

</body>
</html>
