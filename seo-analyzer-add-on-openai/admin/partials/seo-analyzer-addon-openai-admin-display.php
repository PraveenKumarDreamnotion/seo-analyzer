<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
        <?php
        settings_fields('seo_analyzer_addon_openai_options');
        do_settings_sections('seo_analyzer_addon_openai');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">OpenAI API Key</th>
                <td>
                    <input type="text" name="seo_analyzer_addon_openai_api_key" value="<?php echo esc_attr(get_option('seo_analyzer_addon_openai_api_key')); ?>" />
                    <p class="description"><?php _e('Enter your OpenAI API key here.', 'seo-analyzer-add-on-openai'); ?> <a href="https://beta.openai.com/signup" target="_blank"><?php _e('Get an API key', 'seo-analyzer-add-on-openai'); ?></a></p>
                    <button type="button" id="validate-api-key" class="button"><?php _e('Validate API Key', 'seo-analyzer-add-on-openai'); ?></button>
                    <span id="api-key-status"></span>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>