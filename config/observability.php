<?php

$releaseMetadataPath = base_path('bootstrap/release.json');
$releaseMetadata = [];

if (is_file($releaseMetadataPath)) {
    $decodedReleaseMetadata = json_decode((string) file_get_contents($releaseMetadataPath), true);

    if (is_array($decodedReleaseMetadata)) {
        $releaseMetadata = $decodedReleaseMetadata;
    }
}

return [
    'enabled' => (bool) env('OBSERVABILITY_ENABLED', false),
    'service' => env('OBSERVABILITY_SERVICE', 'zencart-site'),

    'request_id' => [
        'header' => 'X-Request-ID',
        'maximum_length' => 128,
    ],

    'release' => [
        'metadata_path' => $releaseMetadataPath,
        'release_id' => $releaseMetadata['release_id'] ?? 'local',
        'deployment_id' => $releaseMetadata['deployment_id'] ?? ($releaseMetadata['release_id'] ?? 'local'),
        'commit_sha' => $releaseMetadata['commit_sha'] ?? 'unknown',
        'built_at' => $releaseMetadata['built_at'] ?? null,
        'deployed_at' => $releaseMetadata['deployed_at'] ?? null,
    ],
];
