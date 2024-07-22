<?php
?>
<div class="stm-cie-page-wrapper">
    <h2><?php echo get_admin_page_title(); ?></h2>

    <div class="stm-cie-import-container" id="stm-cie-import-app" v-cloak>
        <div class="row">
            <div class="col-sm-6">

                <div class="panel panel-info">
                    <div class="panel-body">
                        <div class="input-group form-group">
                            <input readonly="readonly" placeholder="<?php _e('Import file', 'stmcie'); ?>"
                                   class="form-control" name="filename" type="text" v-model="filename">
                            <span class="input-group-btn">
                                <span class="btn btn-default btn-file">
                                    <?php _e('Browse .json', 'stmcie'); ?>
                                    <input accept="application/json" name="import_file"
                                           type="file" ref="import_file" @change="changeInput" required>
                                </span>
                            </span>
                        </div>
                        <div class="form-group">
                            <div class="checkbox">
                                <input type="hidden" value="0" v-model="skip_for_names">
                                <label><input type="checkbox" value="1" v-model="skip_for_names"> <?php _e('Skip if names match', 'stmcie'); ?></label>
                            </div>
                        </div>
                        <div class="form-group">
                            <button class="btn btn-primary" type="button" @click="sendRequest()"><?php _e('Upload', 'stmcie'); ?></button>

                            <span class="stm-cie-import-spinner" v-if="loading"><i class="fa fa-spinner"></i></span>
                        </div>
                    </div>
                </div>

                <div class="alert form-group" :class="'alert-' + message_status" v-if="message">
                    <div v-html="message"></div>
                </div>

                <div class="alert alert-info form-group" v-if="show_info">
                    <div><?php _e('Created:', 'stmcie'); ?></div>
                    <div>- <?php _e('Bundles', 'stmcie'); ?> - <strong v-html="created.bundles"></strong></div>
                    <div>- <?php _e('Courses', 'stmcie'); ?> - <strong v-html="created.courses"></strong></div>
                    <div>- <?php _e('Lessons', 'stmcie'); ?> - <strong v-html="created.lessons"></strong></div>
                    <div>- <?php _e('Quizzes', 'stmcie'); ?> - <strong v-html="created.quizzes"></strong></div>
                    <div>- <?php _e('Questions', 'stmcie'); ?> - <strong v-html="created.questions"></strong></div>

                    <div><?php _e('Skipped:', 'stmcie'); ?></div>
                    <div>- <?php _e('Bundles', 'stmcie'); ?> - <strong v-html="skipped.bundles"></strong></div>
                    <div>- <?php _e('Courses', 'stmcie'); ?> - <strong v-html="skipped.courses"></strong></div>
                    <div>- <?php _e('Lessons', 'stmcie'); ?> - <strong v-html="skipped.lessons"></strong></div>
                    <div>- <?php _e('Quizzes', 'stmcie'); ?> - <strong v-html="skipped.quizzes"></strong></div>
                    <div>- <?php _e('Questions', 'stmcie'); ?> - <strong v-html="skipped.questions"></strong></div>
                </div>

            </div>
        </div>
    </div>
</div>

