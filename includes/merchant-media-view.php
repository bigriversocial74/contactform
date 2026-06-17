<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading"><div><span class="mg-eyebrow">Asset management</span><h1>Media library</h1><p>Inspect images, audio, video, processing status, and published-version usage.</p></div><a class="mg-btn mg-btn-primary" href="/build.php">Upload through builder</a></section>
<div class="mg-product-toolbar"><input type="search" data-asset-search placeholder="Search filenames"><select data-asset-type><option value="all">All media</option><option value="image">Images</option><option value="audio">Audio</option><option value="video">Video</option><option value="download">Downloads</option></select><select data-asset-status><option value="all">All statuses</option><option value="ready">Ready</option><option value="processing">Processing</option><option value="pending">Pending</option><option value="failed">Failed</option><option value="rejected">Rejected</option><option value="retired">Retired</option></select></div>
<section class="mg-app-panel"><div class="mg-app-panel-body"><div class="mg-asset-grid" data-asset-grid></div></div></section>
