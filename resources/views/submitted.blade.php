<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submission received</title>
    <meta http-equiv="refresh" content="10;url={{ $returnUrl }}">
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, Arial, sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            color: #0f172a;
        }

        .card {
            width: min(92vw, 560px);
            padding: 2rem;
            border-radius: 1.25rem;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
            text-align: center;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            margin-bottom: 1rem;
            border-radius: 999px;
            background: #dcfce7;
            font-size: 1.5rem;
        }

        h1 {
            margin: 0 0 0.75rem;
            font-size: 1.75rem;
        }

        p {
            margin: 0 0 1.25rem;
            line-height: 1.6;
            color: #475569;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1rem;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="badge">✓</div>
        <h1>Thanks for your submission</h1>
        <p>Your message has been received and we appreciate your contact.</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ $returnUrl }}">Back to website</a>
        </div>
        <p style="margin-top: 1rem; font-size: 0.95rem;">You’ll be redirected automatically in 10 seconds.</p>
    </main>
</body>
</html>
