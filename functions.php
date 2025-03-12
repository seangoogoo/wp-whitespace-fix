<?php
/*
 * Fix Gutenberg Button Whitespace Issue
 * 
 * This function solves the problem of single spaces in button content being lost
 * when saving posts in the Gutenberg editor. It replaces spaces with &nbsp; entities
 * to ensure they're preserved in the database and displayed correctly on the frontend.
 * 
 * The function works by:
 * 1. Extracting complete button blocks using WordPress comment markers
 * 2. Examining each button to identify those with whitespace-only content
 * 3. Replacing the whitespace with &nbsp; in the complete block
 * 4. Maintaining the original HTML structure while only changing the content
 *
 * @param array $data The post data array being saved to the database
 * @return array The modified post data with preserved button whitespace
 */
function lm_debug_and_fix_buttons($data) {
    //* Only process if this is valid post data with content
    if (!is_array($data) || empty($data['post_content'])) {
        return $data;
    }

    $content = $data['post_content'];

    //* Quick check to avoid unnecessary processing - only proceed if the content contains buttons
    if (strpos($content, 'wp-block-button') === false) {
        return $data;
    }

    write_to_log('==== BUTTON DEBUG: Starting enhanced button analysis ====');

    //* Extract complete button blocks using WordPress comment markers
    //* This approach is better than regex on HTML alone because it captures the full block context
    if (preg_match_all('/<!-- wp:button.*?-->.*?<!-- \/wp:button -->/s', $content, $button_blocks)) {
        write_to_log('Found ' . count($button_blocks[0]) . ' button blocks');

        //* Examine each button block to identify its content
        foreach ($button_blocks[0] as $index => $block) {
            write_to_log("Button block #{$index}: " . $block);

            //* Extract just the content between <a> tags to check if it's whitespace
            if (preg_match('/<a.*?>(.*?)<\/a>/s', $block, $match)) {
                $link_content = $match[1];
                write_to_log("Button #{$index} content: '" . htmlspecialchars($link_content) . "', Length: " . strlen($link_content));

                //* For whitespace content, show character codes for debugging
                //* This helps identify exactly what characters we're dealing with (space, tab, newline, etc.)
                if (trim($link_content) === '' && !empty($link_content)) {
                    $char_codes = [];
                    for ($i = 0; $i < strlen($link_content); $i++) {
                        $char_codes[] = ord($link_content[$i]);
                    }
                    write_to_log("Button #{$index} content char codes: " . implode(', ', $char_codes));
                }
            }
        }

        //* Start with unmodified content, we'll replace specific blocks as needed
        $modified = $content;

        //* Process each button block individually
        foreach ($button_blocks[0] as $block) {
            //* Only target buttons with whitespace-only content between the <a> tags
            //* The \s+ pattern matches one or more whitespace characters (spaces, tabs, newlines)
            if (preg_match('/<a[^>]*>(\s+)<\/a>/s', $block, $match)) {
                $whitespace = $match[1];

                //* Log the exact character codes for transparency
                $codes = [];
                for ($i = 0; $i < strlen($whitespace); $i++) {
                    $codes[] = ord($whitespace[$i]);
                }
                write_to_log("Found whitespace button with chars: " . implode(', ', $codes));

                //* Create a replacement block that's identical except for the content
                //* We use $1 to preserve all attributes of the <a> tag
                $replacement_block = preg_replace('/<a([^>]*)>(\s+)<\/a>/s', '<a$1>&nbsp;</a>', $block);

                //* Use str_replace instead of another regex to ensure we only replace exact matches
                //* This is safer than a global regex replacement which might have unintended consequences
                $modified = str_replace($block, $replacement_block, $modified);

                //* Log before/after for debugging and verification
                write_to_log("Replacing button block: " . htmlspecialchars($block));
                write_to_log("With modified block: " . htmlspecialchars($replacement_block));
            }
        }

        //* Only update the content if we actually made changes
        if ($content !== $modified) {
            write_to_log('Successfully replaced whitespace with &nbsp; in button blocks!');
            $data['post_content'] = $modified;
        } else {
            write_to_log('No whitespace replacements made in button blocks');
        }
    } else {
        write_to_log('No button blocks found using block pattern');
    }

    write_to_log('==== BUTTON DEBUG: Finished enhanced button analysis ====');

    return $data;
}

//* Apply our filter at multiple points in the WordPress save process
//* wp_insert_post_data fires just before WordPress inserts post data into the database
//* content_save_pre fires when content is first being processed for saving
//* Using a high priority (999) ensures our function runs after other filters
add_filter('wp_insert_post_data', 'lm_debug_and_fix_buttons', 999, 1);
add_filter('content_save_pre', 'lm_debug_and_fix_buttons', 999, 1);

?>
