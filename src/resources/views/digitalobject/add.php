<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Digital Object - <?php echo htmlspecialchars($resourceDescription); ?></title>
    <link rel="stylesheet" href="/plugins/arDominionB5Plugin/css/min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .upload-zone {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        .upload-zone.has-file {
            border-color: #198754;
            background: #d1e7dd;
        }
        .upload-zone i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        .upload-zone.has-file i {
            color: #198754;
        }
        .file-info {
            margin-top: 15px;
            padding: 10px;
            background: white;
            border-radius: 4px;
            display: none;
        }
        .file-info.show {
            display: block;
        }
        .progress-container {
            display: none;
            margin-top: 20px;
        }
        .progress-container.show {
            display: block;
        }
        .or-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 30px 0;
        }
        .or-divider::before,
        .or-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }
        .or-divider span {
            padding: 0 15px;
            color: #6c757d;
            font-weight: 500;
        }
        .upload-limits {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row bg-light border-bottom py-3 mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/">Home</a></li>
                        <li class="breadcrumb-item"><a href="/index.php/<?php echo htmlspecialchars($resource->slug); ?>"><?php echo htmlspecialchars($resource->getTitle(['cultureFallback' => true])); ?></a></li>
                        <li class="breadcrumb-item active">Link Digital Object</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-cloud-upload-alt me-2"></i>
                            Link Digital Object
                        </h4>
                        <small class="opacity-75"><?php echo htmlspecialchars($resourceDescription); ?></small>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!$uploadAllowed): ?>
                            <!-- Upload limit reached -->
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($uploadMessage); ?>
                            </div>
                        <?php else: ?>
                            
                            <form id="uploadForm" method="post" enctype="multipart/form-data" 
                                  action="/atom-framework/digitalobject/upload">
                                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="object_id" value="<?php echo $resourceId; ?>">
                                
                                <!-- File Upload Section -->
                                <fieldset class="mb-4">
                                    <legend class="h5 mb-3">
                                        <i class="fas fa-file-upload me-2 text-primary"></i>
                                        Upload a Digital Object
                                    </legend>
                                    
                                    <div class="upload-zone" id="uploadZone">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <h5>Drag and drop file here</h5>
                                        <p class="text-muted mb-2">or click to browse</p>
                                        <input type="file" name="file" id="fileInput" class="d-none">
                                        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('fileInput').click()">
                                            <i class="fas fa-folder-open me-1"></i> Choose File
                                        </button>
                                    </div>
                                    
                                    <div class="file-info" id="fileInfo">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-file fa-2x text-primary me-3"></i>
                                            <div class="flex-grow-1">
                                                <strong id="fileName">-</strong>
                                                <br>
                                                <small class="text-muted" id="fileSize">-</small>
                                            </div>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearFile()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="upload-limits mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Maximum file size: <strong><?php echo htmlspecialchars($maxUploadFormatted); ?></strong>
                                        <?php if ($repository && $repository->uploadLimit > 0): ?>
                                            | Repository limit: <strong><?php echo $repository->uploadLimit; ?> GB</strong>
                                        <?php endif; ?>
                                    </div>
                                </fieldset>
                                
                                <div class="or-divider">
                                    <span>OR</span>
                                </div>
                                
                                <!-- URL Import Section -->
                                <fieldset class="mb-4">
                                    <legend class="h5 mb-3">
                                        <i class="fas fa-link me-2 text-primary"></i>
                                        Link to External Digital Object
                                    </legend>
                                    
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                        <input type="url" name="url" id="urlInput" class="form-control" 
                                               placeholder="https://example.com/image.jpg" value="http://">
                                    </div>
                                    <small class="text-muted">
                                        Enter the full URL to an image, PDF, video, or audio file
                                    </small>
                                </fieldset>
                                
                                <!-- Options -->
                                <fieldset class="mb-4">
                                    <legend class="h5 mb-3">
                                        <i class="fas fa-cog me-2 text-primary"></i>
                                        Options
                                    </legend>
                                    
                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" name="extract_metadata" 
                                               id="extractMetadata" value="1" checked>
                                        <label class="form-check-label" for="extractMetadata">
                                            Extract embedded metadata (EXIF, IPTC, XMP)
                                        </label>
                                    </div>
                                </fieldset>
                                
                                <!-- Progress Bar -->
                                <div class="progress-container" id="progressContainer">
                                    <label class="form-label">Uploading...</label>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                             id="progressBar" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                                    <a href="/index.php/<?php echo htmlspecialchars($resource->slug); ?>" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="fas fa-upload me-1"></i> Create
                                    </button>
                                </div>
                            </form>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const urlInput = document.getElementById('urlInput');
        const form = document.getElementById('uploadForm');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const submitBtn = document.getElementById('submitBtn');
        const maxSize = <?php echo $maxUploadSize; ?>;
        
        // Drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => uploadZone.classList.add('dragover'), false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => uploadZone.classList.remove('dragover'), false);
        });
        
        uploadZone.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });
        
        uploadZone.addEventListener('click', function(e) {
            if (e.target.tagName !== 'BUTTON') {
                fileInput.click();
            }
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleFileSelect(this.files[0]);
            }
        });
        
        function handleFileSelect(file) {
            if (file.size > maxSize) {
                alert('File too large. Maximum size is ' + formatBytes(maxSize));
                clearFile();
                return;
            }
            
            fileName.textContent = file.name;
            fileSize.textContent = formatBytes(file.size);
            fileInfo.classList.add('show');
            uploadZone.classList.add('has-file');
            
            // Clear URL if file selected
            urlInput.value = 'http://';
        }
        
        window.clearFile = function() {
            fileInput.value = '';
            fileInfo.classList.remove('show');
            uploadZone.classList.remove('has-file');
        };
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // URL input - clear file if URL entered
        urlInput.addEventListener('input', function() {
            if (this.value && this.value !== 'http://') {
                clearFile();
            }
        });
        
        // Form submission with progress
        form.addEventListener('submit', function(e) {
            // Only use AJAX if file is being uploaded
            if (fileInput.files.length > 0) {
                e.preventDefault();
                
                const formData = new FormData(form);
                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = percent + '%';
                        progressBar.textContent = percent + '%';
                    }
                });
                
                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        // Redirect on success
                        window.location.href = '/index.php/<?php echo htmlspecialchars($resource->slug); ?>';
                    } else {
                        progressContainer.classList.remove('show');
                        submitBtn.disabled = false;
                        alert('Upload failed: ' + xhr.responseText);
                    }
                });
                
                xhr.addEventListener('error', function() {
                    progressContainer.classList.remove('show');
                    submitBtn.disabled = false;
                    alert('Upload failed. Please try again.');
                });
                
                progressContainer.classList.add('show');
                submitBtn.disabled = true;
                
                xhr.open('POST', form.action, true);
                xhr.send(formData);
            }
            // URL submission goes through normal form post
        });
    });
    </script>
</body>
</html>
