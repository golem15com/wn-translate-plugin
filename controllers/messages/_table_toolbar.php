<script type="text/template" id="<?= $this->getId('tableToolbar') ?>">
    <a
        href="javascript:;"
        data-request="onClearCache"
        data-load-indicator="<?= e(trans('golem15.translate::lang.messages.clear_cache_loading')) ?>"
        class="btn oc-icon-check-square"><?= e(trans('golem15.translate::lang.messages.clear_cache_link')) ?>
    </a>
    <a
        href="javascript:;"
        data-control="popup"
        data-handler="onLoadScanMessagesForm"
        class="btn oc-icon-refresh"><?= e(trans('golem15.translate::lang.messages.scan_messages_link')) ?>
    </a>
    <a
        href="<?= Backend::url('winter/translate/messages/import') ?>"
        class="btn oc-icon-sign-in">
        <?= e(trans('golem15.translate::lang.messages.import_messages_link')) ?>
    </a>
    <a
        href="<?= Backend::url('winter/translate/messages/export') ?>"
        class="btn oc-icon-sign-out">
        <?= e(trans('golem15.translate::lang.messages.export_messages_link')) ?>
    </a>
</script>
