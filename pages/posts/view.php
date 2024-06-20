<?php

# Includes
require 'includes/header.php';
require_once 'classes/post.php';

$mainPost = new Post();
$return = $mainPost->getByShortId($GLOBALS['postShortId']);
if($return == null) {
    header("location: /"); 
}

if($mainPost->headId != null) {
    $headPost = new Post($mainPost->headId);
    if($headPost->headId != null){
        header("location: /post/" . $headPost->shortId); 
    }
}
?>

<section class="section is-fullheight">
    <div class="container is-fullheight">
        <?php 
            if($mainPost->headId != null) {
                $post = new Post($mainPost->headId);
                require 'components/post.php';
            }

            $post = $mainPost;
            require 'components/post.php';

            $textareaDiv = '
                <div class="container mb-5">
                    <textarea class="comment-field textarea has-fixed-size replaceMe" id="content" class="textarea comment" placeholder="Your comment" maxlength="250"></textarea>
                    <button class="button right-side bottom-16 is-primary" onclick="commentIt(event, \'' . $post->shortId . '\')">Comment</button>
                </div>';

            $scriptDiv = '
                <div class="container is-fullheight post-holder">
                    <script class="feed-settings" type="application/json">{"type": "comments", "data": "' . $post->shortId . '", "error": false}</script>
                </div>';

            if ($mainPost->headId == null) {
                echo $textareaDiv;
                echo $scriptDiv;
            } else {
                echo $scriptDiv;
                echo str_replace('replaceMe', 'comment-comment-margin', $textareaDiv);
            }
        ?>
    </div>
</section>

<?php
# Include footer
require 'includes/footer.php';
