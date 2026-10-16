<?php
return [
    'token' => env('METRICPANEL_TOKEN'),
    'api_url' => env('METRICPANEL_API_URL', 'https://api.metricpanel.io'),
    'connection' => env('METRICPANEL_QUEUE_CONNECTION', 'redis'),
    'queue' => env('METRICPANEL_QUEUE', 'metricpanel'),
];
