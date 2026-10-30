<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | CSM System</title>
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        :root { 
            /* The Core SaaS Gradient: Emerald Green to Yellow */
            --saas-gradient: linear-gradient(135deg, #059669 0%, #eab308 100%); 
            --bg-page: #f8fafc; /* Extremely faint blue-gray so the white card pops */
            --text-dark: #1e293b; 
            --text-muted: #64748b;
        }
        
        body { 
            background-color: var(--bg-page); 
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; 
            color: var(--text-dark);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin: 0;
        }

        .error-card {
            background: #ffffff;
            border-radius: 32px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.03), 0 1px 3px rgba(0,0,0,0.02);
            border: 1px solid #f1f5f9;
            padding: 60px 40px;
            max-width: 550px;
            width: 90%;
            text-align: center;
            position: relative;
        }

        /* Applies the Gradient directly to the text */
        .gradient-text {
            background: var(--saas-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .error-number {
            font-size: 8.5rem;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 5px;
            letter-spacing: -3px;
        }

        .floating-icon {
            font-size: 4.5rem;
            animation: float 3.5s ease-in-out infinite;
            display: inline-block;
            margin-bottom: -15px;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
            100% { transform: translateY(0px); }
        }

        /* SaaS Primary Button */
        .btn-gradient {
            background: var(--saas-gradient);
            color: white;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.2);
        }

        .btn-gradient:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.35);
        }

        /* SaaS Secondary Button */
        .btn-outline-saas {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: var(--text-muted);
            transition: all 0.3s ease;
        }

        .btn-outline-saas:hover {
            background-color: #f8fafc;
            color: var(--text-dark);
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body>

    <div class="error-card">
        <!-- Floating Gradient Icon -->
        <div class="floating-icon gradient-text">
            <i class="bi bi-search"></i>
        </div>
        
        <!-- Large Gradient 404 -->
        <div class="error-number gradient-text">404</div>
        
        <!-- Messages -->
        <h2 class="fw-bold mb-3">Page Not Found</h2>
        <p class="mb-4 fs-6" style="color: var(--text-muted); max-width: 400px; margin: 0 auto; line-height: 1.6;">
            We couldn't find the page you're looking for. It might have been moved, deleted, or perhaps the URL is incorrect.
        </p>
        
        <!-- Modern Navigation Buttons -->
        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mt-5">
            <!-- Button to Public Form -->
            <a href="index.php" class="btn btn-gradient btn-lg rounded-pill px-4 fw-bold fs-6">
                <i class="bi bi-house-door me-2"></i> Go to Homepage
            </a>
            
            <!-- Button to Dashboard (for Admins) -->

        </div>
    </div>

</body>
</html>