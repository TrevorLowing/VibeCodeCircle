<?php
/**
 * Generate manifest.json for deployment package
 *
 * Usage: php generate-manifest.php <deployment-directory>
 */

if ( $argc < 2 ) {
    fwrite( STDERR, "Usage: php generate-manifest.php <deployment-directory>\n" );
    exit( 1 );
}

$deployment_dir = rtrim( $argv[1], '/\\' );
if ( ! is_dir( $deployment_dir ) ) {
    fwrite( STDERR, "Error: Deployment directory not found: {$deployment_dir}\n" );
    exit( 1 );
}

$manifest = array(
    'version' => '1.0.0',
    'build_date' => gmdate( 'Y-m-d\TH:i:s\Z' ),
    'package_type' => 'deployment',
    'files' => array(),
    'checksums' => array(),
);

// Get project name from directory
$project_name = basename( $deployment_dir );
$project_name = preg_replace( '/-deployment$/', '', $project_name );
$manifest['project_name'] = $project_name;

// Scan files and calculate checksums
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator( $deployment_dir, FilesystemIterator::SKIP_DOTS ),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ( $iterator as $item ) {
    if ( $item->isFile() ) {
        $file_path = $item->getPathname();
        $relative_path = str_replace( $deployment_dir . DIRECTORY_SEPARATOR, '', $file_path );
        $relative_path = str_replace( '\\', '/', $relative_path );

        // Skip manifest.json itself
        if ( $relative_path === 'manifest.json' ) {
            continue;
        }

        $file_size = $item->getSize();
        $file_mtime = $item->getMTime();
        $checksum = md5_file( $file_path );

        $manifest['files'][] = array(
            'path' => $relative_path,
            'size' => $file_size,
            'modified' => gmdate( 'Y-m-d\TH:i:s\Z', $file_mtime ),
        );

        $manifest['checksums'][ $relative_path ] = $checksum;
    }
}

// Count files by type
$file_counts = array(
    'pages' => 0,
    'css' => 0,
    'js' => 0,
    'images' => 0,
    'theme' => 0,
);

foreach ( $manifest['files'] as $file ) {
    $path = $file['path'];
    if ( strpos( $path, 'pages/' ) === 0 ) {
        $file_counts['pages']++;
    } elseif ( strpos( $path, 'assets/css/' ) === 0 ) {
        $file_counts['css']++;
    } elseif ( strpos( $path, 'assets/js/' ) === 0 ) {
        $file_counts['js']++;
    } elseif ( strpos( $path, 'assets/images/' ) === 0 ) {
        $file_counts['images']++;
    } elseif ( strpos( $path, 'theme/' ) === 0 ) {
        $file_counts['theme']++;
    }
}

$manifest['file_counts'] = $file_counts;
$manifest['total_files'] = count( $manifest['files'] );

// Output JSON
echo json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
