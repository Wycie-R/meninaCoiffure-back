<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menina Coiffure — Documentación de la API (Swagger)</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: 'Outfit', sans-serif;
        }
        .header-banner {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
            color: #ffffff;
            padding: 1.25rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-title h1 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .header-tag {
            background: rgba(255, 255, 255, 0.15);
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .header-links a {
            color: #e0e7ff;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.2s ease;
        }
        .header-links a:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }
        #swagger-ui {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1rem 1.5rem 3rem;
        }
        .swagger-ui .topbar {
            display: none !important;
        }
    </style>
</head>
<body>
    <header class="header-banner">
        <div class="header-title">
            <span style="font-size: 1.6rem;">✂️</span>
            <h1>Menina Coiffure — API REST</h1>
            <span class="header-tag">Avance al 18/09/2026</span>
        </div>
        <div class="header-links">
            <a href="/openapi.json" target="_blank">📄 Ver OpenAPI JSON</a>
        </div>
    </header>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            window.ui = SwaggerUIBundle({
                url: "/openapi.json",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "BaseLayout",
                docExpansion: "list",
                filter: true,
                showRequestHeaders: true,
                tryItOutEnabled: true
            });
        };
    </script>
</body>
</html>
