<?php
/*
Plugin Name: Vikinghammer Tweet
Plugin URI: http://vh-tweet.vikinghammer.com
Description: Submit tweets in response to any new posts or comments on your blog.
Version: 0.2.4
Author: Sean Schulte
Author URI: http://www.vikinghammer.com
*/

require_once(dirname(__FILE__) . '/include/VikinghammerUrlShortener.class.php');
require_once(dirname(__FILE__) . '/include/class.twitter.php');

/**
Register the settings menu link for the options page.
*/
function vh_tweet_plugin_menu() {
    add_options_page('VH Tweet Options', 'VH Tweet', 8, __FILE__, 'vh_tweet_plugin_options');
}

/**
Display the options page where the user can enter their Twitter credentials.
*/
function vh_tweet_plugin_options() {
    $display = '';
    $hidden_field_name = 'vh_tweet_hidden_field_name';

    if ($_POST[$hidden_field_name] == 'Y') {
        update_option('vh_tweet_username', $_POST['vh_tweet_username']);
        update_option('vh_tweet_password', $_POST['vh_tweet_password']);
        update_option('vh_tweet_post', ($_POST['vh_tweet_post'] == 1 ? 1 : 0));
        update_option('vh_tweet_comment', ($_POST['vh_tweet_comment'] == 1 ? 1 : 0));

        $display .= '<div class="updated"><p><strong>Options saved.</strong></p></div>';
    }

    $display .= '<div class="wrap">';
    $display .= '<h2>VH Tweet Settings</h2>';
    $display .= '<p>Enter Twitter credentials for your blog, so each comment and post can send out a tweet.</p>';
    $display .= '<form method="post">';
    $display .= '<table cellspacing="2" cellpadding="2" border="0">';
    $display .= '<input type="hidden" name="' . $hidden_field_name . '" value="Y" />';
    $display .= '<tr><td>Username:</td><td><input type="text" name="vh_tweet_username" value="' . get_option('vh_tweet_username') . '" /></td></tr>';
    $display .= '<tr><td>Password:</td><td><input type="password" name="vh_tweet_password" value="' . get_option('vh_tweet_password') . '" /></td></tr>';
    $display .= '<tr><td align="right"><input type="checkbox" id="vh_tweet_post" name="vh_tweet_post" value="1" ' . (get_option('vh_tweet_post') == 1 ? 'checked="checked"' : '') . ' /></td><td><label for="vh_tweet_post">Send a tweet with each new post</label></td></tr>';
    $display .= '<tr><td align="right"><input type="checkbox" id="vh_tweet_comment" name="vh_tweet_comment" value="1" ' . (get_option('vh_tweet_comment') == 1 ? 'checked="checked"' : '') . ' /></td><td><label for="vh_tweet_comment">Send a tweet with each new comment</label></td></tr>';
    $display .= '<tr><td colspan="2"><input type="submit" value="Save" class="button-primary" /></td></tr>';
    $display .= '<input type="hidden" name="action" value="update" />';
    $display .= '<input type="hidden" name="page_options" value="vh_tweet_username,vh_tweet_password" />';
    $display .= '</table>';
    $display .= '</form>';
    $display .= '</div>';

    echo $display;
}

/**
When a new comment is posted, we want to send off a tweet to notify our followers about it.
*/
function vh_tweet_comment($commentId, $approvalStatus=null) {
    $twitterUsername = get_option('vh_tweet_username');
    $twitterPassword = get_option('vh_tweet_password');
    $tweetComment = get_option('vh_tweet_comment');

    // make sure they want to send a tweet for comments
    if (!$tweetComment) {
        return;
    }

    // if they haven't set up a username or password, we're not doing anything
    if (!$twitterUsername || !$twitterPassword) {
        return;
    }

    // we only want to tweet if the comment is approved
    if (!$approvalStatus) {
        $approvalStatus = wp_get_comment_status($commentId);
    }
    if (($approvalStatus == 1) || ($approvalStatus == 'approve') || ($approvalStatus == 'approved')) {
        // get the comment
        $comment = get_comment($commentId);

        if (!$comment) {
            return;
        }

        // load the post
        $post = get_post($comment->comment_post_ID);
        $link = get_permalink($comment->comment_post_ID);

        // if we couldn't load the post, we can't do anything
        if (!$post) {
            return;
        }

        // if we got the link, shorten it
        if ($link) {
            $link .= "#comment-{$commentId}";
            $shortener = new VikinghammerUrlShortener();
            $link = $shortener->shortenUrl($link);
        }

        // build the full tweet
        $tweet = "New comment on {$post->post_title} by {$comment->comment_author} {$link}";

        // if the tweet was too long, take out the title
        if (strlen($tweet) > 140) {
            $tweet = "New comment by {$comment->comment_author} {$link}";
        }

        // if it is still too long, just put in the link
        if (strlen($tweet) > 140) {
            $tweet = "New comment {$link}";
        }

        // send it off to twitter
        $twitter = new twitter();
        $twitter->username = $twitterUsername;
        $twitter->password = $twitterPassword;

        $twitter->update($tweet);
    }
}

/**
When a new post is saved, send off a tweet to twitter about it.
This only works if you've entered your Twitter credentials.
*/
function vh_tweet_post($postId) {
    $twitterUsername = get_option('vh_tweet_username');
    $twitterPassword = get_option('vh_tweet_password');
    $tweetPost = get_option('vh_tweet_post');

    // if they don't want to send a tweet for each post, we're out of here
    if (!$tweetPost) {
        return;
    }

    // if they haven't entered a username, we're not doing anything
    if (!$twitterUsername || !$twitterPassword) {
        return;
    }

    // get the post
    $post = get_post($postId);
    $link = get_permalink($postId);

    // if we couldn't find the post, we're not doing anything
    if (!$post) {
        return;
    }

    // if we got the link, shorten it
    if ($link) {
        $shortener = new VikinghammerUrlShortener();
        $link = $shortener->shortenUrl($link);
    }

    // get the author, and if you can't find it then we're out of here
    $user = get_userdata($post->post_author);
    if (!$user) {
        return;
    }

    // build the full tweet
    $tweet = "New post by {$user->user_login}: {$post->post_title} {$link}";

    // if the tweet is too long, take out the title
    if (strlen($tweet) > 140) {
        $tweet = "New post by {$user->user_login} {$link}";
    }

    // send it off to twitter
    $twitter = new twitter();
    $twitter->username = $twitterUsername;
    $twitter->password = $twitterPassword;

    $twitter->update($tweet);
}

// connect to the comment so we're posting to twitter about the comment
add_action('comment_post', 'vh_tweet_comment');

// connect to the post so we're posting to twitter about the post
add_action('publish_post', 'vh_tweet_post');

// activate the admin menu for this plugin
add_action('admin_menu', 'vh_tweet_plugin_menu');

?>
