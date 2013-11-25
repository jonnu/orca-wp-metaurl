
    <div id="orca_wp_meta_admin">

        <div class="orca-row">
            <label for="orca_wp_meta_url">Meta URL</label>
            <input name="orca_wp_meta" id="orca_wp_meta_url" value="<?php echo $value; ?>" data-default-value="http://" />
            <input type="hidden" name="orca_wp_meta_nonce" value="<?php echo $nonce; ?>" />
        </div>

        <div class="orca-row">
            <label for="orca_wp_meta_position">Position</label>
            <select name="orca_wp_meta_position" id="orca_wp_meta_position">
                <option value="">Default</option>
                <option value="left"<?php echo $value_position == 'left' ? ' selected="selected"' : ''; ?>>Left</option>
                <option value="right"<?php echo $value_position == 'right' ? ' selected="selected"' : ''; ?>>Right</option>
            </select>
        </div>

    </div>
