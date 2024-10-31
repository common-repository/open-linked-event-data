<div class="wrap">
    <h1><?php _e('OLE Export','oleexport') ?></h1>
    <hr/>

    <form id="oleexport-options" action="" method="post">
        <?php wp_nonce_field('oleexport_options_edit', 'oleexport_options_edit_field') ?>

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Active', 'oleexport' ) ?></th>
                <td>
                    <label for="active">
                        <input type="checkbox" name="active" id="active" size="30" <?php echo WordPressOleExportUtil::getOption('active') === 1 ? 'checked="checked"' : '' ?>/>
                        <?php _e('Enables public access','oleexport') ?>
                    </label>
                    <p><?php _e('Feed will be accessible without WordPress-Login and automatically registered on <a href="http://www.hinto.ch" target="_blank">www.hinto.ch</a>.','oleexport') ?></p>
                    <p><?php _e('<strong>Recommendation</strong><br>Please add the following text to the imprint or footer of your website to support the concept of OLE.<br><blockquote>This website is part of the <a href="/olelicense.html" target="_blank">Open Linked Event Data Network (OLE)</a>, an initiative of the <a href="https://www.hinto.ch" target="_blank">Hinto</a> association. All events are also automatically published on <a href="https://www.hinto.ch" target="_blank">www.hinto.ch</a>.</blockquote>','oleexport') ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="driver"><?php _e( 'Driver', 'oleexport' ) ?></label>
                </th>
                <td>
                    <select name="driver" id="driver">
                        <?php foreach ($this->getActiveDrivers() as $driver) : ?>
                            <option value="<?php echo $driver->className; ?>" <?php echo WordPressOleExportUtil::getOption('driver') === $driver->className ? 'selected="selected"' : ''; ?> <?php echo $driver->disabled ? 'disabled="disabled"' : ''; ?>><?php echo $driver->displayName; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <p><?php _e( 'Feed Url', 'oleexport' ) ?>: <a href="<?php echo home_url('feed/ole'); ?>" target="_blank"><?php echo home_url('feed/ole'); ?></a></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e( 'OLE Export Checkbox', 'oleexport' ) ?></th>
                <td>
                    <label for="post_checkbox">
                        <input type="checkbox" name="post_checkbox" id="post_checkbox" size="30" <?php echo WordPressOleExportUtil::getOption('post_checkbox') === 1 ? 'checked="checked"' : '' ?>/>
                        <?php _e('Adds a checkbox to event posts to select events which shall be distributed over OLE.','oleexport') ?>
                    </label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e( 'OLE Export Source Version', 'oleexport' ) ?></th>
                <td>
                    <label for="post_checkbox">
                        <input type="textfield" name="source_version" id="source_version" size="30" value="<?php echo WordPressOleExportUtil::getOption('source_version') ?>"/>
                    </label>
                    <p><?php _e('Change value to force complete reimport by OLE clients.','oleexport') ?></p>
                </td>
            </tr>
        </table>

        <p class="submit"><input type="submit" name="optionsEdit" value="<?php _e( 'Save', 'oleexport' ) ?>" class="button-primary" /></p>
    </form>
</div>
