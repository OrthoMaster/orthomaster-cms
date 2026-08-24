<style>
    .panel-heading .text-muted { padding: 0px 0px 20px 15px; }
    .panel-bordered { border: 1px solid #e8e8e8; }
    .panel-heading p { color: #888; margin: 0; }

    /* Filter tree */
    #filter-tree .ftree-node {
        margin-bottom: 14px;
        border-left: 3px solid #5eead4;
        padding-left: 12px;
    }
    /* Nested dependants sit inside their parent's box, offset far enough
       that the chain (Zone > Segment > Localisation > Pathologie) reads
       at a glance. */
    #filter-tree .ftree-children {
        margin-top: 12px;
        padding-left: 14px;
        border-left: 1px dashed #cfd2db;
    }
    #filter-tree .ftree-children > .ftree-node {
        border-left-color: #cfd2db;
        margin-bottom: 12px;
    }
    #filter-tree .ftree-children > .ftree-node:last-child {
        margin-bottom: 0;
    }
    /* Depth tint — each level a step cooler than the last. */
    #filter-tree .ftree-node[data-depth="1"] { border-left-color: #93c5fd; }
    #filter-tree .ftree-node[data-depth="2"] { border-left-color: #c4b5fd; }
    #filter-tree .ftree-node[data-depth="3"] { border-left-color: #f0abfc; }
    #filter-tree .ftree-node[data-depth="4"] { border-left-color: #cfd2db; }

    #filter-tree .ftree-title {
        font-weight: 600;
        color: #2D3142;
        margin-bottom: 6px;
        font-size: 13px;
    }
    #filter-tree .ftree-node[data-depth="0"] > .ftree-title {
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
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
        margin: 0;
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