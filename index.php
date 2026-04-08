<?php
// Bill of Materials PHP version - Homepage
// Created by Engr. Alex
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOM/BOQ System - Home</title>
    <script>try{var t=localStorage.getItem('bom_theme');if(t&&t!=='default')document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link rel="stylesheet" href="themes.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: url('https://serverx.ratfish-regulus.ts.net/') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Dark overlay for better text readability */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }

        .homepage-container {
            position: relative;
            z-index: 10;
            text-align: center;
            padding: 40px;
        }

        .logo-section {
            margin-bottom: 30px;
        }

        .logo-section h1 {
            font-size: 3rem;
            color: #ffffff;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.7);
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        .logo-section p {
            font-size: 1.2rem;
            color: #142880;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.7);
        }

        .button-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 420px;
            margin: 0 auto;
        }

        .nav-btn {
            padding: 18px 40px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .bom-btn {
            background: linear-gradient(135deg, #63399b, #63399b);
            color: white;
        }

        .bom-btn:hover {
            background: linear-gradient(135deg, #ffd000, #e6bc00);
            color: #333;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 208, 0, 0.4);
        }

        .boq-btn {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
        }

        .boq-btn:hover {
            background: linear-gradient(135deg, #ffd000, #e6bc00);
            color: #333;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 208, 0, 0.4);
        }

        .viewsave-btn {
            background: linear-gradient(135deg, #42cf2f, #42cf2f);
            color: white;
        }

        .viewsave-btn:hover {
            background: linear-gradient(135deg, #333, #000);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .viewprice-btn {
            background: linear-gradient(135deg, #c6c935, #c6c935);
            color: white;
        }

        .viewprice-btn:hover {
            background: linear-gradient(135deg, #333, #000);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }
        .viewlogs-btn {
            background: linear-gradient(135deg, #fc7100, #fc7100);
            color: white;
        }

        .viewlogs-btn:hover {
            background: linear-gradient(135deg, #333, #000);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }
    .viewaccomplishment-btn {
            background: linear-gradient(135deg, #ff0000, #ff0000);
            color: white;
        }

        .viewaccomplishment-btn:hover {
            background: linear-gradient(135deg, #333, #ff0000);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }
        .footer-text {
            margin-top: 40px;
            color: #ccc;
            font-size: 0.9rem;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .logo-section h1 {
                font-size: 2rem;
            }
            .button-container {
                max-width: 350px;
            }
            .nav-btn {
                padding: 15px 30px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="homepage-container">
        <div class="logo-section">
            <h1>BOM / BOQ System</h1>
            <p>Bill of Materials & Bill of Quantities Management</p>
        </div>

        <div class="button-container">
            <button class="nav-btn bom-btn" onclick="loadPage('bom.php')">Create BOM</button>
            <button class="nav-btn boq-btn" onclick="loadPage('bom_labor.php')">Create BOQ</button>
            <button class="nav-btn viewsave-btn" onclick="loadPage('save_bomboq.php')">View Save Files</button>
            <button class="nav-btn viewprice-btn" onclick="loadPage('price_edit_bomboq.php')">View MCE</button>
            <button class="nav-btn viewlogs-btn" onclick="loadPage('user_logs.php')">View Logs</button>
            <button class="nav-btn viewaccomplishment-btn" onclick="loadPage('https://serverx.ratfish-regulus.ts.net/')">VIEW ACCOMPLISHMENT REPORT</button>
            <button class="nav-btn viewaccomplishment-btn" onclick="loadPage('https://serverx.ratfish-regulus.ts.net/site2')">VIEW DAC HOME PAGE</button>
            <button class="nav-btn theme-trigger-btn" onclick="BomThemes.open()">&#127912; Select Themes</button>
        </div>

        <p class="footer-text">Created by Engr. Alex &copy; 2026</p>
    </div>

    <script>
        function loadPage(pageUrl) {
            window.location.href = pageUrl;
        }
    </script>
    <script src="themes.js"></script>
</body>
</html>