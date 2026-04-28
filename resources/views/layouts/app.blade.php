<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Qbits Application')</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }

        nav {
            background-color: #2c3e50;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        nav a {
            color: white !important;
        }

        nav a:hover {
            color: #ecf0f1 !important;
        }

        .dropdown-menu {
            background-color: #34495e !important;
            border: none;
        }

        .dropdown-item {
            color: white !important;
        }

        .dropdown-item:hover,
        .dropdown-item:focus {
            background-color: #2c3e50 !important;
            color: #ecf0f1 !important;
        }

        .container {
            max-width: 1200px;
        }

        footer {
            background-color: #2c3e50;
            color: white;
            margin-top: 50px;
            padding: 20px 0;
            text-align: center;
        }
    </style>

    @yield('styles')
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ url('/') }}">
                <strong>Qbits</strong>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <!--<li class="nav-item">
                        <a class="nav-link" href="{{ url('/') }}">Home</a>
                    </li> -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="telemetryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Telemetry
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="telemetryDropdown">
                            <li><a class="dropdown-item" href="{{ route('telemetry.history') }}">History</a></li>
                            <li><a class="dropdown-item" href="{{ route('telemetry.heartbeat') }}">Heartbeat</a></li>
                        </ul>
                    </li>
                    <!--<li class="nav-item">
                        <a class="nav-link" href="#">API Docs</a>
                    </li> -->
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="py-4">
        @yield('content')
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    @yield('scripts')
</body>
</html>
