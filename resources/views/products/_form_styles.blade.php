<style>
    .panel-bordered { border: 1px solid #e8e8e8; }
    .panel-heading p { color: #888; margin: 0; }

    /* Filter tree */
    #filter-tree .ftree-node {
        margin-bottom: 14px;
        border-left: 3px solid #5eead4;
        padding-left: 12px;
    }
    #filter-tree .ftree-node.indent {
        margin-left: 24px;
        border-left-color: #cfd2db;
    }
    #filter-tree .ftree-title {
        font-weight: 600;
        color: #2D3142;
        margin-bottom: 6px;
        font-size: 13px;
    }
    #filter-tree .ftree-title .ftree-meta {
        font-weight: 400;
        font-size: 11px;
        color: #8b95a8;
        margin-left: 6px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }
    #filter-tree .ftree-options {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    #filter-tree .ftree-pill {
        cursor: pointer;
        user-select: none;
        padding: 5px 12px;
        border-radius: 999px;
        background: #f3f0ea;
        border: 1.5px solid transparent;
        font-size: 12.5px;
        color: #4a5278;
        transition: all 0.15s ease;
    }
    #filter-tree .ftree-pill:hover {
        border-color: #5eead4;
    }
    #filter-tree .ftree-pill.checked {
        background: #5eead4;
        color: #0a0f24;
        border-color: #5eead4;
        font-weight: 600;
    }
</style>