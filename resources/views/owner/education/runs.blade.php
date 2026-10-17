<?php
// Fixed tags and escaped text only; template indentation must not insert text between runs.
foreach ($runs as $run) {
    if ($run['href']) {
        echo '<a href="'.e($run['href']).'" rel="noopener noreferrer" class="underline break-words">';
    }
    if ($run['bold']) {
        echo '<strong>';
    }
    if ($run['italic']) {
        echo '<em>';
    }
    echo e($run['text']);
    if ($run['italic']) {
        echo '</em>';
    }
    if ($run['bold']) {
        echo '</strong>';
    }
    if ($run['href']) {
        echo '</a>';
    }
}
