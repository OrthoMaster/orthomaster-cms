<style>
    /* --- Boutons d'action fixés en bas à droite --- */
    .action-wrapper-absolute {
        position: fixed;
        z-index: 9999;
        right: 40px;
        bottom: 30px;
        display: flex;
        gap: 8px;
        padding: 10px 12px;
        background: rgba(255, 255, 255, .96);
        border: 1px solid #e3e6ec;
        border-radius: 8px;
        box-shadow: 0 4px 18px rgba(0, 0, 0, .12);
    }

    .action-wrapper-absolute .btn { margin: 0; }

    .page-content.edit-add { padding-bottom: 90px; }

    /* --- Visuel --- */
    .infographic-preview {
        max-width: 100%;
        max-height: 420px;
        border: 1px solid #e3e6ec;
        border-radius: 4px;
        background: #f7f9fc;
    }
</style>