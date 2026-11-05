<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>xTIPP REST API</title>
    <link rel="stylesheet" href="{{ route('plugin-rest-api.docs.asset', ['file' => 'swagger-ui.css']) }}">
    <style>
        body { margin: 0; }
        .rest-api-bar { padding: 10px 20px; background: #f3f4f6; border-bottom: 1px solid #d1d5db; font-family: sans-serif; font-size: 14px; }
        .rest-api-bar a { color: #1d4ed8; margin-right: 16px; }
    </style>
</head>
<body>
<div class="rest-api-bar">
    <a href="{{ url('/') }}">xTIPP</a>
    <a href="{{ route('plugin-rest-api.keys') }}">{{ trans('rest-api::ui.keys.sidename') }}</a>
</div>
<div id="swagger-ui" data-spec="{{ route('plugin-rest-api.docs.spec') }}"></div>
<script src="{{ route('plugin-rest-api.docs.asset', ['file' => 'swagger-ui-bundle.js']) }}"></script>
<script src="{{ route('plugin-rest-api.docs.asset', ['file' => 'docs.js']) }}"></script>
</body>
</html>
