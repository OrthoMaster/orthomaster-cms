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

    /* --- Réponses --- */
    .answer-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .answer-correct {
        flex-shrink: 0;
        width: 34px;
        height: 34px;
        margin: 0;
        border: 1px solid #e3e6ec;
        border-radius: 4px;
        background: #f7f9fc;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .answer-correct input { margin: 0; cursor: pointer; }

    .answer-row.is-correct .answer-correct {
        background: #e8f8ee;
        border-color: #2ECC71;
    }

    .answer-row.is-correct .answer-text {
        border-color: #2ECC71;
    }

    .answer-remove {
        flex-shrink: 0;
        padding: 6px 8px;
        color: #b9bfc7;
    }

    .answer-remove:hover { color: #E74C3C; }

    .answers-hint {
        display: inline-block;
        margin-left: 12px;
        margin-bottom: 0;
    }
</style>