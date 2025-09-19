class VideoBlock {
    constructor({ data, api, config }) {
        this.api = api;
        this.data = data || {};
        this.config = config || {};
        this.wrapper = null;
        this.currentMode = this.data.type || null; // 'url' or 'upload'
    }

    static get toolbox() {
        return {
            title: 'Vidéo',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
  <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
  <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" />
</svg>
`
        };
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('video-block');

        if (this.data.type && (this.data.url || this.data.video_id)) {
            this.renderVideoDisplay();
        } else {
            this.renderModeSelection();
        }

        return this.wrapper;
    }

    renderModeSelection() {
        this.wrapper.innerHTML = `
      <div class="video-mode-selection">
        <h4>Ajouter Vidéo</h4>
        <div class="mode-buttons">
          <button class="mode-btn url-mode" data-mode="url">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
              <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
            </svg>
            En ligne
            <small>YouTube, etc.</small>
          </button>
          
          <button class="mode-btn upload-mode disabled" data-mode="upload" disabled>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7,10 12,15 17,10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Importer
            <small>Bientôt disponible ...</small>
          </button>
        </div>
      </div>
    `;

        // Attach listeners
        this.wrapper.querySelector('.url-mode').addEventListener('click', () => {
            this.currentMode = 'url';
            this.renderUrlInput();
        });
    }

    renderUrlInput() {
        this.wrapper.innerHTML = `
      <div class="video-url-input">
        <div class="input-header">
          <h4>URL de la vidéo</h4>
          <button class="back-btn">← Retour</button>
        </div>
        
        <div class="url-form">
          <input type="url" 
                 placeholder="Collez le lien YouTube ou un lien vidéo direct …" 
                 class="video-url-field"
                 value="${this.data.url || ''}" />
          
          <div class="url-options">
              <input type="text" 
                     placeholder="Titre de la vidéo (facultatif)" 
                     class="video-title-field"
                     value="${this.data.title || ''}" />
          </div>
          
          <div class="text-right">
            <button class="preview-btn btn btn-sm btn-warning" disabled>Prévisualisation</button>
            <button class="save-btn btn btn-sm btn-primary" disabled>Enregistrer</button>
          </div>
        </div>
        
        <div class="video-preview-area" style="display: none;"></div>
      </div>
    `;

        this.attachUrlInputListeners();
    }

    attachUrlInputListeners() {
        const urlField = this.wrapper.querySelector('.video-url-field');
        const titleField = this.wrapper.querySelector('.video-title-field');
        const previewBtn = this.wrapper.querySelector('.preview-btn');
        const saveBtn = this.wrapper.querySelector('.save-btn');
        const backBtn = this.wrapper.querySelector('.back-btn');
        const previewArea = this.wrapper.querySelector('.video-preview-area');

        // Back button
        backBtn.addEventListener('click', () => {
            this.renderModeSelection();
        });

        // URL validation
        const validateUrl = () => {
            const url = urlField.value.trim();
            const isValid = this.isValidVideoUrl(url);

            previewBtn.disabled = !isValid;
            saveBtn.disabled = !isValid;

            return isValid;
        };

        urlField.addEventListener('input', validateUrl);

        // Preview functionality
        previewBtn.addEventListener('click', () => {
            const url = urlField.value.trim();
            if (this.isValidVideoUrl(url)) {
                this.showVideoPreview(url, titleField.value.trim());
            }
        });

        // Save functionality
        saveBtn.addEventListener('click', () => {
            const url = urlField.value.trim();
            const title = titleField.value.trim();

            if (this.isValidVideoUrl(url)) {
                this.data = {
                    type: 'url',
                    url: url,
                    title: title || this.extractTitleFromUrl(url),
                    embedUrl: this.getEmbedUrl(url)
                };
                this.renderVideoDisplay();
            }
        });

        // Initial validation
        validateUrl();
    }

    isValidVideoUrl(url) {
        if (!url) return false;

        // YouTube patterns
        const youtubePatterns = [
            /^https?:\/\/(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)/,
            /^https?:\/\/(www\.)?youtube\.com\/embed\//
        ];

        // Direct video file patterns
        const directVideoPatterns = [
            /\.(mp4|webm|ogg|avi|mov)(\?.*)?$/i
        ];

        return youtubePatterns.some(pattern => pattern.test(url)) ||
            directVideoPatterns.some(pattern => pattern.test(url));
    }

    getEmbedUrl(url) {
        // YouTube
        const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\n?#]+)/);
        if (youtubeMatch) {
            return `https://www.youtube.com/embed/${youtubeMatch[1]}`;
        }

        // Direct video
        return url;
    }

    extractTitleFromUrl(url) {
        // Simple title extraction
        try {
            const urlObj = new URL(url);
            return urlObj.hostname.replace('www.', '') + ' video';
        } catch {
            return 'Video';
        }
    }

    showVideoPreview(url, title) {
        const previewArea = this.wrapper.querySelector('.video-preview-area');
        const embedUrl = this.getEmbedUrl(url);

        let previewContent;

        if (embedUrl.includes('youtube.com')) {
            // Embedded video
            previewContent = `
        <div class="embed-preview">
          <iframe src="${embedUrl}" 
                  width="100%" 
                  height="315" 
                  frameborder="0" 
                  allowfullscreen>
          </iframe>
        </div>
      `;
        } else {
            // Direct video
            previewContent = `
        <div class="direct-video-preview">
          <video controls width="100%" height="315">
            <source src="${url}" type="video/mp4">
            Your browser does not support the video tag.
          </video>
        </div>
      `;
        }

        previewArea.innerHTML = `
      <div class="preview-header">
      </div>
      ${previewContent}
    `;

        previewArea.style.display = 'block';
    }

    renderVideoDisplay() {
        const { url, title, embedUrl, type } = this.data;

        let videoContent;

        if (embedUrl && (embedUrl.includes('youtube.com'))) {
            videoContent = `
        <iframe src="${embedUrl}" 
                width="100%" 
                height="315" 
                frameborder="0" 
                allowfullscreen>
        </iframe>
      `;
        } else if (url) {
            videoContent = `
        <video controls width="100%" height="315">
          <source src="${url}" type="video/mp4">
          Votre navigateur ne supporte pas la balise vidéo.
        </video>
      `;
        }

        this.wrapper.innerHTML = `
      <div class="video-display">
        <div class="video-container">
          ${videoContent}
        </div>
        <div class="video-info">
          <div class="video-title">${title}</div>
          <div class="video-actions">
            <button class="edit-video-btn">Modifier</button>
          </div>
        </div>
      </div>
    `;

        // Attach action listeners
        this.wrapper.querySelector('.edit-video-btn').addEventListener('click', () => {
            this.renderUrlInput();
        });
    }

    save() {
        return this.data;
    }

    static get sanitize() {
        return {
            type: false,
            url: false,
            title: false,
            embedUrl: false,
            video_id: false
        };
    }
}