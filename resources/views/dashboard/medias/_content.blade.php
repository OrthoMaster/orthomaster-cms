{{-- Éditeur de contenu — uniquement en édition, une fois la vidéo créée --}}
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title" style="display:inline-block">
                    <i class="voyager-character"></i> Contenu
                </h3>
                <div class="dev-mode-toggle pull-right" style="padding: 20px 20px 0 0">
                    <label class="switch">
                        <input type="checkbox" id="devModeToggle">
                        <span class="slider round"></span>
                    </label>
                    <span class="toggle-label">DEV mode</span>
                </div>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-12" id="editorColumn">
                        <div id="editorjs"></div>
                    </div>
                    <div class="col-md-5" id="previewColumn" style="display:none">
                        <div id="codePreview"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Contenu de l'éditeur, sérialisé au moment de la soumission --}}
<input type="hidden" name="content_blocks" id="content_blocks">
