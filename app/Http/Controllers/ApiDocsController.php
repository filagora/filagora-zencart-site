<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class ApiDocsController extends Controller
{
    public function pluginMarketplace(): Response
    {
        $specUrl = route('docs.api.plugin-marketplace.spec');
        $escapedSpecUrl = e($specUrl);

        return response(<<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plugin Marketplace API Docs</title>
    <style>
        body {
            margin: 0;
            background: #f4f1ea;
            color: #1f2933;
            font-family: Georgia, "Times New Roman", serif;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #d5cbbb;
            background:
                linear-gradient(135deg, rgba(119, 141, 169, 0.18), rgba(222, 199, 164, 0.3)),
                #fbf8f2;
        }

        .title {
            margin: 0;
            font-size: 1.2rem;
            letter-spacing: 0.02em;
        }

        .subtitle {
            margin: 0.25rem 0 0;
            font-size: 0.92rem;
            color: #52606d;
        }

        .link {
            color: #8b5e34;
            text-decoration: none;
            font-weight: 600;
            white-space: nowrap;
        }

        .link:hover,
        .link:focus {
            text-decoration: underline;
        }

        #redoc-container {
            min-height: calc(100vh - 80px);
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div>
            <h1 class="title">Plugin Marketplace API</h1>
            <p class="subtitle">Read-oriented catalog, release, download, update-check, and optional subscription contract.</p>
        </div>
        <a class="link" href="{$escapedSpecUrl}">Raw OpenAPI YAML</a>
    </div>

    <div id="redoc-container"></div>

    <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
    <script>
        Redoc.init({$this->jsonLiteral($specUrl)}, {
            hideDownloadButton: false,
            pathInMiddlePanel: true,
            theme: {
                colors: {
                    primary: {
                        main: '#8b5e34'
                    }
                },
                typography: {
                    fontFamily: 'Georgia, "Times New Roman", serif',
                    headings: {
                        fontFamily: 'Georgia, "Times New Roman", serif'
                    },
                    code: {
                        fontFamily: '"SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace'
                    }
                }
            }
        }, document.getElementById('redoc-container'));
    </script>
</body>
</html>
HTML);
    }

    public function pluginMarketplaceSpec(): Response
    {
        $path = base_path('docs/openapi-plugin-marketplace.yaml');

        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }

    private function jsonLiteral(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
