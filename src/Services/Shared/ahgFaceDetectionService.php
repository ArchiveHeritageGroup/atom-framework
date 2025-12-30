<?php

/**
 * Face Detection Service for AtoM
 *
 * Detects faces in images and links them to authority records
 * Supports multiple backends: Local (OpenCV), AWS Rekognition, Azure, Google Cloud Vision
 *
 * @package    arMetadataExtractorPlugin
 * @subpackage lib/services
 * @author     Johan Pieterse <johan@theahg.co.za>
 * @version    1.0
 */

use Illuminate\Database\Capsule\Manager as DB;

class ahgFaceDetectionService
{
    // Detection backends
    const BACKEND_LOCAL = 'local';           // OpenCV via Python
    const BACKEND_AWS = 'aws_rekognition';   // AWS Rekognition
    const BACKEND_AZURE = 'azure';           // Azure Face API
    const BACKEND_GOOGLE = 'google';         // Google Cloud Vision

    // Term IDs
    const TERM_NAME_ACCESS_POINT_ID = 177;

    protected $backend;
    protected $config = [];
    protected $errors = [];

    /**
     * Constructor
     *
     * @param string $backend Detection backend to use
     * @param array $config Backend configuration
     */
    public function __construct($backend = self::BACKEND_LOCAL, $config = [])
    {
        $this->backend = $backend;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        $uploadDir = class_exists('sfConfig')
            ? sfConfig::get('sf_upload_dir')
            : sfConfig::get('sf_upload_dir', sfConfig::get('sf_root_dir') . '/uploads');

        return [
            // Local (OpenCV) settings
            'python_path' => '/usr/bin/python3',
            'opencv_cascade' => '/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml',
            'min_face_size' => 30,
            'scale_factor' => 1.1,
            'min_neighbors' => 5,

            // AWS Rekognition settings
            'aws_region' => 'us-east-1',
            'aws_collection_id' => 'atom_faces',
            'aws_quality_filter' => 'AUTO',

            // Azure Face API settings
            'azure_endpoint' => '',
            'azure_key' => '',

            // Google Cloud Vision settings
            'google_credentials' => '',

            // General settings
            'max_faces' => 20,
            'confidence_threshold' => 0.7,
            'save_face_crops' => true,
            'face_crop_path' => 'uploads/faces',
            'uploads_dir' => $uploadDir,
        ];
    }

    /**
     * Get uploads directory
     */
    protected function getUploadsDir(): string
    {
        return $this->config['uploads_dir'] ?? (class_exists('sfConfig')
            ? sfConfig::get('sf_upload_dir')
            : sfConfig::get('sf_upload_dir', sfConfig::get('sf_root_dir') . '/uploads'));
    }

    /**
     * Get errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Detect faces in an image
     *
     * @param string $imagePath Path to image file
     * @return array Array of detected faces with coordinates and attributes
     */
    public function detectFaces($imagePath): array
    {
        if (!file_exists($imagePath)) {
            $this->errors[] = 'Image file not found: ' . $imagePath;
            return [];
        }

        switch ($this->backend) {
            case self::BACKEND_LOCAL:
                return $this->detectWithOpenCV($imagePath);
            case self::BACKEND_AWS:
                return $this->detectWithAwsRekognition($imagePath);
            case self::BACKEND_AZURE:
                return $this->detectWithAzure($imagePath);
            case self::BACKEND_GOOGLE:
                return $this->detectWithGoogle($imagePath);
            default:
                $this->errors[] = 'Unknown backend: ' . $this->backend;
                return [];
        }
    }

    /**
     * Match detected faces against known authority records
     *
     * @param array $faces Detected faces from detectFaces()
     * @param string $imagePath Original image path
     * @return array Faces with matched authority records
     */
    public function matchToAuthorities($faces, $imagePath): array
    {
        $matched = [];

        foreach ($faces as $face) {
            // Extract face image
            $faceImage = $this->extractFaceImage($imagePath, $face);

            if (!$faceImage) {
                $face['matches'] = [];
                $matched[] = $face;
                continue;
            }

            // Search for matches in authority records
            $matches = $this->searchAuthorityFaces($faceImage, $face);

            $face['matches'] = $matches;
            $face['face_image'] = $faceImage;

            $matched[] = $face;
        }

        return $matched;
    }

    /**
     * Index a face for an authority record
     *
     * @param string $imagePath Path to image with face
     * @param int $authorityId AtoM actor ID
     * @param array $faceCoords Optional face coordinates
     * @return bool Success
     */
    public function indexAuthorityFace($imagePath, $authorityId, $faceCoords = null): bool
    {
        // Detect face if coords not provided
        if (!$faceCoords) {
            $faces = $this->detectFaces($imagePath);
            if (empty($faces)) {
                $this->errors[] = 'No face detected in image';
                return false;
            }
            // Use first (largest) face
            $faceCoords = $faces[0];
        }

        // Extract and save face image
        $faceImage = $this->extractFaceImage($imagePath, $faceCoords);

        if (!$faceImage) {
            return false;
        }

        // Store face encoding in database
        return $this->storeFaceEncoding($authorityId, $faceImage, $faceCoords);
    }

    // =========================================================================
    // LOCAL DETECTION (OpenCV via Python)
    // =========================================================================

    /**
     * Detect faces using OpenCV
     */
    protected function detectWithOpenCV($imagePath): array
    {
        // Check for Python and OpenCV
        $pythonPath = $this->config['python_path'];

        if (!file_exists($pythonPath) && !$this->commandExists('python3')) {
            $this->errors[] = 'Python not found';
            return $this->detectWithOpenCVFallback($imagePath);
        }

        // Create Python script for face detection
        $script = $this->generateOpenCVScript();
        $scriptPath = sys_get_temp_dir() . '/atom_face_detect.py';
        file_put_contents($scriptPath, $script);

        // Run detection
        $escapedImage = escapeshellarg($imagePath);
        $cascadePath = escapeshellarg($this->config['opencv_cascade']);
        $minSize = (int) $this->config['min_face_size'];
        $scaleFactor = (float) $this->config['scale_factor'];
        $minNeighbors = (int) $this->config['min_neighbors'];

        $cmd = "$pythonPath $scriptPath $escapedImage $cascadePath $minSize $scaleFactor $minNeighbors 2>&1";
        $output = shell_exec($cmd);

        // Clean up
        @unlink($scriptPath);

        if (!$output) {
            $this->errors[] = 'OpenCV detection returned no output';
            return $this->detectWithOpenCVFallback($imagePath);
        }

        // Parse JSON output
        $result = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = 'Failed to parse OpenCV output: ' . $output;
            return $this->detectWithOpenCVFallback($imagePath);
        }

        if (isset($result['error'])) {
            $this->errors[] = 'OpenCV error: ' . $result['error'];
            return [];
        }

        return $result['faces'] ?? [];
    }

    /**
     * Generate Python script for OpenCV face detection
     */
    protected function generateOpenCVScript(): string
    {
        return <<<'PYTHON'
#!/usr/bin/env python3
import sys
import json
import cv2
import numpy as np

def detect_faces(image_path, cascade_path, min_size, scale_factor, min_neighbors):
    try:
        # Load image
        img = cv2.imread(image_path)
        if img is None:
            return {"error": "Failed to load image"}
        
        # Convert to grayscale
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # Load cascade classifier
        face_cascade = cv2.CascadeClassifier(cascade_path)
        if face_cascade.empty():
            # Try default OpenCV path
            face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
        
        if face_cascade.empty():
            return {"error": "Failed to load face cascade"}
        
        # Detect faces
        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=scale_factor,
            minNeighbors=min_neighbors,
            minSize=(min_size, min_size),
            flags=cv2.CASCADE_SCALE_IMAGE
        )
        
        # Format results
        result = {"faces": []}
        
        for i, (x, y, w, h) in enumerate(faces):
            face_data = {
                "id": i,
                "bounding_box": {
                    "x": int(x),
                    "y": int(y),
                    "width": int(w),
                    "height": int(h)
                },
                "confidence": 0.9,  # OpenCV doesn't provide confidence
                "center": {
                    "x": int(x + w/2),
                    "y": int(y + h/2)
                }
            }
            
            # Try to detect face landmarks using dlib if available
            try:
                import dlib
                import os
                predictor_path = "/usr/share/dlib/shape_predictor_68_face_landmarks.dat"
                if os.path.exists(predictor_path):
                    predictor = dlib.shape_predictor(predictor_path)
                    rect = dlib.rectangle(x, y, x+w, y+h)
                    shape = predictor(gray, rect)
                    
                    landmarks = []
                    for j in range(68):
                        landmarks.append({
                            "x": shape.part(j).x,
                            "y": shape.part(j).y
                        })
                    face_data["landmarks"] = landmarks
            except:
                pass
            
            result["faces"].append(face_data)
        
        result["image_size"] = {
            "width": img.shape[1],
            "height": img.shape[0]
        }
        
        return result
        
    except Exception as e:
        return {"error": str(e)}

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Usage: script.py <image_path> <cascade_path> [min_size] [scale_factor] [min_neighbors]"}))
        sys.exit(1)
    
    image_path = sys.argv[1]
    cascade_path = sys.argv[2]
    min_size = int(sys.argv[3]) if len(sys.argv) > 3 else 30
    scale_factor = float(sys.argv[4]) if len(sys.argv) > 4 else 1.1
    min_neighbors = int(sys.argv[5]) if len(sys.argv) > 5 else 5
    
    result = detect_faces(image_path, cascade_path, min_size, scale_factor, min_neighbors)
    print(json.dumps(result))
PYTHON;
    }

    /**
     * Fallback face detection using PHP (basic)
     */
    protected function detectWithOpenCVFallback($imagePath): array
    {
        // This is a very basic fallback - not as accurate as OpenCV
        // Uses edge detection heuristics

        $imageInfo = @getimagesize($imagePath);
        if (!$imageInfo) {
            return [];
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        // Load image
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng($imagePath);
                break;
            default:
                return [];
        }

        if (!$img) {
            return [];
        }

        // Convert to grayscale and detect skin-tone regions
        // This is a very simplified approach
        $skinRegions = [];
        $step = 10; // Sample every 10 pixels for speed

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Simple skin tone detection
                if ($this->isSkinTone($r, $g, $b)) {
                    $skinRegions[] = ['x' => $x, 'y' => $y];
                }
            }
        }

        imagedestroy($img);

        if (empty($skinRegions)) {
            return [];
        }

        // Cluster skin regions to find faces
        $faces = $this->clusterSkinRegions($skinRegions, $width, $height);

        return $faces;
    }

    /**
     * Check if RGB values represent skin tone
     */
    protected function isSkinTone($r, $g, $b): bool
    {
        // Simple skin tone detection rules
        // Works for various skin tones

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);

        // Rule 1: RGB thresholds
        if ($r <= 95 || $g <= 40 || $b <= 20) {
            return false;
        }

        // Rule 2: R > G > B typically for skin
        if ($r <= $g || $r <= $b) {
            return false;
        }

        // Rule 3: Difference between max and min
        if ($max - $min <= 15) {
            return false;
        }

        // Rule 4: R-G difference
        if (abs($r - $g) <= 15) {
            return false;
        }

        return true;
    }

    /**
     * Cluster skin regions to identify faces
     */
    protected function clusterSkinRegions($regions, $imageWidth, $imageHeight): array
    {
        if (count($regions) < 10) {
            return [];
        }

        // Simple clustering using grid-based approach
        $gridSize = 50;
        $grid = [];

        foreach ($regions as $point) {
            $gx = floor($point['x'] / $gridSize);
            $gy = floor($point['y'] / $gridSize);
            $key = "$gx,$gy";

            if (!isset($grid[$key])) {
                $grid[$key] = 0;
            }
            $grid[$key]++;
        }

        // Find dense clusters
        $faces = [];
        $threshold = count($regions) / 20; // Adaptive threshold

        foreach ($grid as $key => $count) {
            if ($count >= $threshold) {
                list($gx, $gy) = explode(',', $key);

                // Estimate face bounding box
                $cx = ($gx + 0.5) * $gridSize;
                $cy = ($gy + 0.5) * $gridSize;
                $faceSize = min($imageWidth, $imageHeight) / 5; // Estimate

                $faces[] = [
                    'id' => count($faces),
                    'bounding_box' => [
                        'x' => (int) max(0, $cx - $faceSize / 2),
                        'y' => (int) max(0, $cy - $faceSize / 2),
                        'width' => (int) $faceSize,
                        'height' => (int) $faceSize,
                    ],
                    'confidence' => min(1.0, $count / ($threshold * 2)),
                    'center' => [
                        'x' => (int) $cx,
                        'y' => (int) $cy,
                    ],
                    'detection_method' => 'skin_tone_clustering',
                ];
            }
        }

        // Sort by confidence
        usort($faces, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        // Return top faces
        return array_slice($faces, 0, $this->config['max_faces']);
    }

    // =========================================================================
    // AWS REKOGNITION
    // =========================================================================

    /**
     * Detect faces using AWS Rekognition
     */
    protected function detectWithAwsRekognition($imagePath): array
    {
        if (!class_exists('Aws\\Rekognition\\RekognitionClient')) {
            $this->errors[] = 'AWS SDK not available';
            return [];
        }

        try {
            $client = new Aws\Rekognition\RekognitionClient([
                'region' => $this->config['aws_region'],
                'version' => 'latest',
            ]);

            $imageBytes = file_get_contents($imagePath);

            $result = $client->detectFaces([
                'Image' => [
                    'Bytes' => $imageBytes,
                ],
                'Attributes' => ['ALL'],
            ]);

            $faces = [];
            $imageInfo = getimagesize($imagePath);
            $width = $imageInfo[0];
            $height = $imageInfo[1];

            foreach ($result['FaceDetails'] as $i => $face) {
                $box = $face['BoundingBox'];

                $faces[] = [
                    'id' => $i,
                    'bounding_box' => [
                        'x' => (int) ($box['Left'] * $width),
                        'y' => (int) ($box['Top'] * $height),
                        'width' => (int) ($box['Width'] * $width),
                        'height' => (int) ($box['Height'] * $height),
                    ],
                    'confidence' => $face['Confidence'] / 100,
                    'attributes' => [
                        'age_range' => $face['AgeRange'] ?? null,
                        'gender' => $face['Gender'] ?? null,
                        'emotions' => $face['Emotions'] ?? [],
                        'smile' => $face['Smile'] ?? null,
                        'eyeglasses' => $face['Eyeglasses'] ?? null,
                        'sunglasses' => $face['Sunglasses'] ?? null,
                        'beard' => $face['Beard'] ?? null,
                        'mustache' => $face['Mustache'] ?? null,
                    ],
                    'landmarks' => $face['Landmarks'] ?? [],
                    'pose' => $face['Pose'] ?? null,
                    'quality' => $face['Quality'] ?? null,
                ];
            }

            return $faces;

        } catch (Exception $e) {
            $this->errors[] = 'AWS Rekognition error: ' . $e->getMessage();
            return [];
        }
    }

    // =========================================================================
    // AZURE FACE API
    // =========================================================================

    /**
     * Detect faces using Azure Face API
     */
    protected function detectWithAzure($imagePath): array
    {
        $endpoint = $this->config['azure_endpoint'];
        $key = $this->config['azure_key'];

        if (!$endpoint || !$key) {
            $this->errors[] = 'Azure Face API not configured';
            return [];
        }

        $url = rtrim($endpoint, '/') . '/face/v1.0/detect';
        $url .= '?returnFaceId=true&returnFaceLandmarks=true&returnFaceAttributes=age,gender,smile,facialHair,glasses,emotion';

        $imageBytes = file_get_contents($imagePath);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $imageBytes,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Ocp-Apim-Subscription-Key: ' . $key,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->errors[] = 'Azure API error: HTTP ' . $httpCode;
            return [];
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            $this->errors[] = 'Invalid Azure response';
            return [];
        }

        $faces = [];

        foreach ($data as $i => $face) {
            $rect = $face['faceRectangle'];

            $faces[] = [
                'id' => $i,
                'face_id' => $face['faceId'] ?? null,
                'bounding_box' => [
                    'x' => $rect['left'],
                    'y' => $rect['top'],
                    'width' => $rect['width'],
                    'height' => $rect['height'],
                ],
                'confidence' => 1.0, // Azure doesn't return confidence for detection
                'landmarks' => $face['faceLandmarks'] ?? null,
                'attributes' => $face['faceAttributes'] ?? null,
            ];
        }

        return $faces;
    }

    // =========================================================================
    // GOOGLE CLOUD VISION
    // =========================================================================

    /**
     * Detect faces using Google Cloud Vision
     */
    protected function detectWithGoogle($imagePath): array
    {
        $credentials = $this->config['google_credentials'];

        if (!$credentials) {
            $this->errors[] = 'Google Cloud Vision not configured';
            return [];
        }

        // Use Google Cloud Vision API
        if (!class_exists('Google\\Cloud\\Vision\\V1\\ImageAnnotatorClient')) {
            $this->errors[] = 'Google Cloud Vision SDK not available';
            return [];
        }

        try {
            $imageAnnotator = new Google\Cloud\Vision\V1\ImageAnnotatorClient([
                'credentials' => $credentials,
            ]);

            $imageContent = file_get_contents($imagePath);
            $response = $imageAnnotator->faceDetection($imageContent);
            $faces = $response->getFaceAnnotations();

            $result = [];
            $imageInfo = getimagesize($imagePath);

            foreach ($faces as $i => $face) {
                $vertices = $face->getBoundingPoly()->getVertices();

                $minX = $minY = PHP_INT_MAX;
                $maxX = $maxY = 0;

                foreach ($vertices as $vertex) {
                    $minX = min($minX, $vertex->getX());
                    $minY = min($minY, $vertex->getY());
                    $maxX = max($maxX, $vertex->getX());
                    $maxY = max($maxY, $vertex->getY());
                }

                $result[] = [
                    'id' => $i,
                    'bounding_box' => [
                        'x' => $minX,
                        'y' => $minY,
                        'width' => $maxX - $minX,
                        'height' => $maxY - $minY,
                    ],
                    'confidence' => $face->getDetectionConfidence(),
                    'attributes' => [
                        'joy' => $face->getJoyLikelihood(),
                        'sorrow' => $face->getSorrowLikelihood(),
                        'anger' => $face->getAngerLikelihood(),
                        'surprise' => $face->getSurpriseLikelihood(),
                        'headwear' => $face->getHeadwearLikelihood(),
                    ],
                    'landmarks' => $this->parseGoogleLandmarks($face->getLandmarks()),
                    'roll_angle' => $face->getRollAngle(),
                    'pan_angle' => $face->getPanAngle(),
                    'tilt_angle' => $face->getTiltAngle(),
                ];
            }

            $imageAnnotator->close();

            return $result;

        } catch (Exception $e) {
            $this->errors[] = 'Google Vision error: ' . $e->getMessage();
            return [];
        }
    }

    /**
     * Parse Google Vision landmarks
     */
    protected function parseGoogleLandmarks($landmarks): array
    {
        $result = [];

        foreach ($landmarks as $landmark) {
            $result[] = [
                'type' => $landmark->getType(),
                'x' => $landmark->getPosition()->getX(),
                'y' => $landmark->getPosition()->getY(),
                'z' => $landmark->getPosition()->getZ(),
            ];
        }

        return $result;
    }

    // =========================================================================
    // FACE MATCHING AND STORAGE
    // =========================================================================

    /**
     * Extract face image from full image
     */
    protected function extractFaceImage($imagePath, $face): ?string
    {
        $imageInfo = @getimagesize($imagePath);
        if (!$imageInfo) {
            return null;
        }

        // Load image
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($imagePath);
                break;
            default:
                return null;
        }

        if (!$src) {
            return null;
        }

        $box = $face['bounding_box'];

        // Add padding
        $padding = 0.2;
        $padX = (int) ($box['width'] * $padding);
        $padY = (int) ($box['height'] * $padding);

        $x = max(0, $box['x'] - $padX);
        $y = max(0, $box['y'] - $padY);
        $w = min($imageInfo[0] - $x, $box['width'] + $padX * 2);
        $h = min($imageInfo[1] - $y, $box['height'] + $padY * 2);

        // Create face image
        $faceImg = imagecreatetruecolor($w, $h);
        imagecopy($faceImg, $src, 0, 0, $x, $y, $w, $h);

        imagedestroy($src);

        // Save if configured
        if ($this->config['save_face_crops']) {
            $uploadsDir = $this->getUploadsDir();
            $outputDir = $uploadsDir . '/' . $this->config['face_crop_path'];
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $filename = 'face_' . uniqid() . '.jpg';
            $outputPath = $outputDir . '/' . $filename;

            imagejpeg($faceImg, $outputPath, 90);

            $relativePath = $this->config['face_crop_path'] . '/' . $filename;
        } else {
            $relativePath = null;
        }

        imagedestroy($faceImg);

        return $relativePath;
    }

    /**
     * Search for matching faces in authority records
     */
    protected function searchAuthorityFaces($faceImagePath, $faceData): array
    {
        $matches = [];

        try {
            // Get indexed faces from database
            $indexedFaces = DB::table('actor_face_index as afi')
                ->join('actor_i18n as ai', function ($join) {
                    $join->on('afi.actor_id', '=', 'ai.id')
                        ->where('ai.culture', '=', 'en');
                })
                ->where('afi.is_active', 1)
                ->select('afi.*', 'ai.authorized_form_of_name')
                ->get();

            foreach ($indexedFaces as $row) {
                // Compare faces (simplified - in production use face encodings)
                $similarity = $this->compareFaces($faceImagePath, $row->face_image_path);

                if ($similarity >= $this->config['confidence_threshold']) {
                    $matches[] = [
                        'actor_id' => $row->actor_id,
                        'actor_name' => $row->authorized_form_of_name,
                        'similarity' => $similarity,
                        'indexed_face_id' => $row->id,
                    ];
                }
            }
        } catch (Exception $e) {
            // Table might not exist
            $this->errors[] = 'Face search error: ' . $e->getMessage();
        }

        // Sort by similarity
        usort($matches, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return $matches;
    }

    /**
     * Compare two face images
     */
    protected function compareFaces($faceImage1, $faceImage2): float
    {
        // In production, this would use face encodings/embeddings
        // This is a placeholder using simple histogram comparison

        $uploadsDir = $this->getUploadsDir();
        $path1 = $uploadsDir . '/' . $faceImage1;
        $path2 = $uploadsDir . '/' . $faceImage2;

        if (!file_exists($path1) || !file_exists($path2)) {
            return 0;
        }

        // Load images
        $img1 = @imagecreatefromjpeg($path1);
        $img2 = @imagecreatefromjpeg($path2);

        if (!$img1 || !$img2) {
            return 0;
        }

        // Resize to same dimensions
        $size = 64;
        $thumb1 = imagecreatetruecolor($size, $size);
        $thumb2 = imagecreatetruecolor($size, $size);

        imagecopyresampled($thumb1, $img1, 0, 0, 0, 0, $size, $size, imagesx($img1), imagesy($img1));
        imagecopyresampled($thumb2, $img2, 0, 0, 0, 0, $size, $size, imagesx($img2), imagesy($img2));

        imagedestroy($img1);
        imagedestroy($img2);

        // Calculate histogram correlation
        $hist1 = $this->calculateHistogram($thumb1);
        $hist2 = $this->calculateHistogram($thumb2);

        imagedestroy($thumb1);
        imagedestroy($thumb2);

        return $this->correlateHistograms($hist1, $hist2);
    }

    /**
     * Calculate grayscale histogram
     */
    protected function calculateHistogram($img): array
    {
        $hist = array_fill(0, 256, 0);
        $width = imagesx($img);
        $height = imagesy($img);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Convert to grayscale
                $gray = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
                $hist[$gray]++;
            }
        }

        // Normalize
        $total = $width * $height;
        for ($i = 0; $i < 256; $i++) {
            $hist[$i] /= $total;
        }

        return $hist;
    }

    /**
     * Calculate histogram correlation
     */
    protected function correlateHistograms($hist1, $hist2): float
    {
        $mean1 = array_sum($hist1) / 256;
        $mean2 = array_sum($hist2) / 256;

        $num = 0;
        $den1 = 0;
        $den2 = 0;

        for ($i = 0; $i < 256; $i++) {
            $d1 = $hist1[$i] - $mean1;
            $d2 = $hist2[$i] - $mean2;

            $num += $d1 * $d2;
            $den1 += $d1 * $d1;
            $den2 += $d2 * $d2;
        }

        if ($den1 == 0 || $den2 == 0) {
            return 0;
        }

        return ($num / sqrt($den1 * $den2) + 1) / 2; // Normalize to 0-1
    }

    /**
     * Store face encoding in database
     */
    protected function storeFaceEncoding($authorityId, $faceImagePath, $faceCoords): bool
    {
        // Create table if not exists
        $this->ensureFaceIndexTable();

        try {
            DB::table('actor_face_index')->insert([
                'actor_id' => $authorityId,
                'face_image_path' => $faceImagePath,
                'bounding_box' => json_encode($faceCoords['bounding_box']),
                'confidence' => $faceCoords['confidence'] ?? 1.0,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (Exception $e) {
            $this->errors[] = 'Failed to store face: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * Ensure face index table exists
     */
    protected function ensureFaceIndexTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS actor_face_index (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_id INT NOT NULL,
            face_image_path VARCHAR(500) NULL,
            bounding_box JSON NULL,
            face_encoding BLOB NULL,
            confidence FLOAT DEFAULT 1.0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (actor_id) REFERENCES actor(id) ON DELETE CASCADE,
            INDEX idx_actor (actor_id),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        try {
            DB::statement($sql);
        } catch (Exception $e) {
            // Table might already exist
        }
    }

    /**
     * Check if command exists
     */
    protected function commandExists($cmd): bool
    {
        $return = shell_exec(sprintf("which %s 2>/dev/null", escapeshellarg($cmd)));
        return !empty($return);
    }

    /**
     * Link detected faces to information object
     */
    public function linkFacesToInformationObject($faces, $informationObjectId): int
    {
        $linked = 0;

        foreach ($faces as $face) {
            if (empty($face['matches'])) {
                continue;
            }

            // Use best match
            $match = $face['matches'][0];

            if ($match['similarity'] < $this->config['confidence_threshold']) {
                continue;
            }

            // Create name access point
            try {
                // Check if relation already exists
                $exists = DB::table('relation')
                    ->where('subject_id', $informationObjectId)
                    ->where('object_id', $match['actor_id'])
                    ->where('type_id', self::TERM_NAME_ACCESS_POINT_ID)
                    ->exists();

                if (!$exists) {
                    // Create object entry first
                    $objectId = DB::table('object')->insertGetId([
                        'class_name' => 'QubitRelation',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                    // Create relation
                    DB::table('relation')->insert([
                        'id' => $objectId,
                        'subject_id' => $informationObjectId,
                        'object_id' => $match['actor_id'],
                        'type_id' => self::TERM_NAME_ACCESS_POINT_ID,
                    ]);

                    $linked++;
                }
            } catch (Exception $e) {
                $this->errors[] = 'Failed to link face: ' . $e->getMessage();
            }
        }

        return $linked;
    }

    /**
     * Get all indexed faces for an actor
     */
    public function getActorFaces(int $actorId): array
    {
        return DB::table('actor_face_index')
            ->where('actor_id', $actorId)
            ->where('is_active', 1)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Delete indexed face
     */
    public function deleteFaceIndex(int $faceIndexId): bool
    {
        return DB::table('actor_face_index')
            ->where('id', $faceIndexId)
            ->delete() > 0;
    }

    /**
     * Deactivate indexed face
     */
    public function deactivateFaceIndex(int $faceIndexId): bool
    {
        return DB::table('actor_face_index')
            ->where('id', $faceIndexId)
            ->update(['is_active' => 0]) > 0;
    }
}