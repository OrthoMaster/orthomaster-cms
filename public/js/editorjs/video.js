class VideoBlock {
    constructor({ data, api, config }) {
        this.api = api;
        this.data = data || {};
        this.config = config || {};
        this.wrapper = null;
        this.currentMode = this.data.type || null; // 'url', 'hls'
        this.hlsInstance = null;
    }

    static get toolbox() {
        return {
            title: 'Vidéo',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
  <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
  <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" />
</svg>`
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
            <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
            </svg>
            En ligne
            <small>YouTube, etc.</small>
          </button>
          
          <button class="mode-btn hls-mode" data-mode="hls">
            <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
            </svg>
            Vidéo HLS
            <small>OrthoMaster</small>
          </button>
        </div>
      </div>
    `;

        // Attach listeners
        this.wrapper.querySelector('.url-mode').addEventListener('click', () => {
            this.currentMode = 'url';
            this.renderUrlInput();
        });

        this.wrapper.querySelector('.hls-mode').addEventListener('click', () => {
            this.currentMode = 'hls';
            this.renderHlsInput();
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

    renderHlsInput() {
        this.wrapper.innerHTML = `
      <div class="video-hls-input">
        <div class="input-header">
          <h4>Vidéo HLS Streaming</h4>
          <button class="back-btn">← Retour</button>
        </div>
        
        <div class="hls-form">
          <input type="url" 
                 placeholder="URL du fichier .m3u8 ou lien HLS …" 
                 class="video-hls-field"
                 value="${this.data.type === 'hls' ? this.data.url : ''}" />
          
          <div class="hls-options">
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

        this.attachHlsInputListeners();
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

    attachHlsInputListeners() {
        const hlsField = this.wrapper.querySelector('.video-hls-field');
        const titleField = this.wrapper.querySelector('.video-title-field');
        const previewBtn = this.wrapper.querySelector('.preview-btn');
        const saveBtn = this.wrapper.querySelector('.save-btn');
        const backBtn = this.wrapper.querySelector('.back-btn');
        const previewArea = this.wrapper.querySelector('.video-preview-area');

        // Back button
        backBtn.addEventListener('click', () => {
            this.cleanupHls();
            this.renderModeSelection();
        });

        // HLS URL validation
        const validateHlsUrl = () => {
            const url = hlsField.value.trim();
            const isValid = this.isValidHlsUrl(url);

            previewBtn.disabled = !isValid;
            saveBtn.disabled = !isValid;

            return isValid;
        };

        hlsField.addEventListener('input', validateHlsUrl);

        // Preview functionality
        previewBtn.addEventListener('click', () => {
            const url = hlsField.value.trim();
            if (this.isValidHlsUrl(url)) {
                this.showHlsPreview(url, titleField.value.trim());
            }
        });

        // Save functionality
        saveBtn.addEventListener('click', () => {
            const url = hlsField.value.trim();
            const title = titleField.value.trim();

            if (this.isValidHlsUrl(url)) {
                this.data = {
                    type: 'hls',
                    url: url,
                    title: title || this.extractTitleFromUrl(url)
                };
                this.renderVideoDisplay();
            }
        });

        // Initial validation
        validateHlsUrl();
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

    isValidHlsUrl(url) {
        if (!url) return false;

        // HLS patterns
        const hlsPatterns = [
            /\.m3u8(\?.*)?$/i,
            /^https?:\/\/.*\/.*\.m3u8/i
        ];

        return hlsPatterns.some(pattern => pattern.test(url));
    }

    // Load HLS.js dynamically if needed
    async loadHlsJs() {
        if (window.Hls) {
            return window.Hls;
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/hls.js@latest';
            script.onload = () => resolve(window.Hls);
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async showHlsPreview(url, title) {
        const previewArea = this.wrapper.querySelector('.video-preview-area');

        previewArea.innerHTML = `
      <div class="preview-header">
        <div class="loading-indicator">Chargement de la vidéo HLS...</div>
      </div>
      <div class="hls-video-preview">
        <video id="hls-preview-video" controls width="100%" height="315">
          Votre navigateur ne supporte pas la vidéo HLS.
        </video>
        <div class="hls-fallback" style="display: none;">
          <p>⚠️ Votre navigateur ne supporte pas HLS nativement.</p>
          <p>La vidéo sera chargée avec HLS.js</p>
        </div>
      </div>
    `;

        previewArea.style.display = 'block';

        const video = previewArea.querySelector('#hls-preview-video');
        const loadingIndicator = previewArea.querySelector('.loading-indicator');
        const fallbackDiv = previewArea.querySelector('.hls-fallback');

        try {
            // Check if browser supports HLS natively (Safari)
            if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = url;
                loadingIndicator.textContent = 'Chargement natif HLS...';
            } else {
                // Use HLS.js for other browsers
                const Hls = await this.loadHlsJs();

                if (Hls.isSupported()) {
                    this.cleanupHls();
                    this.hlsInstance = new Hls({
                        debug: false,
                        enableWorker: true
                    });

                    this.hlsInstance.loadSource(url);
                    this.hlsInstance.attachMedia(video);

                    this.hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                        loadingIndicator.style.display = 'none';
                    });

                    this.hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                        console.error('HLS Error:', data);
                        loadingIndicator.textContent = 'Erreur lors du chargement de la vidéo';
                        fallbackDiv.style.display = 'block';
                    });

                    fallbackDiv.style.display = 'block';
                    loadingIndicator.textContent = 'Chargement avec HLS.js...';
                } else {
                    throw new Error('HLS not supported');
                }
            }

            // Hide loading indicator when video is ready
            video.addEventListener('loadeddata', () => {
                loadingIndicator.style.display = 'none';
            });

        } catch (error) {
            console.error('Error loading HLS:', error);
            loadingIndicator.textContent = 'Erreur: Impossible de charger la vidéo HLS';
            fallbackDiv.style.display = 'block';
            fallbackDiv.innerHTML = `
                <p>❌ Impossible de lire cette vidéo HLS</p>
                <p>Vérifiez que l'URL est correcte et accessible</p>
                <details>
                    <summary>Détails de l'erreur</summary>
                    <pre>${error.message}</pre>
                </details>
            `;
        }
    }

    cleanupHls() {
        if (this.hlsInstance) {
            this.hlsInstance.destroy();
            this.hlsInstance = null;
        }
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
                  height="400" 
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

    async renderVideoDisplay() {
        const { url, title, embedUrl, type } = this.data;

        let videoContent;

        if (type === 'hls') {
            // HLS Video Display
            videoContent = `
        <video id="hls-display-video" controls width="100%" height="315">
          Votre navigateur ne supporte pas la vidéo HLS.
        </video>
        <div class="hls-status" style="display: none;">
          <small>🔄 Chargement HLS...</small>
        </div>
      `;
        } else if (embedUrl && (embedUrl.includes('youtube.com'))) {
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

        // Initialize HLS for display if needed
        if (type === 'hls') {
            await this.initializeHlsDisplay(url);
        }

        // Attach action listeners
        this.wrapper.querySelector('.edit-video-btn').addEventListener('click', () => {
            this.cleanupHls();
            if (type === 'hls') {
                this.renderHlsInput();
            } else {
                this.renderUrlInput();
            }
        });
    }

    async initializeHlsDisplay(url) {
        const video = this.wrapper.querySelector('#hls-display-video');
        const statusDiv = this.wrapper.querySelector('.hls-status');

        if (!video || !url) return;

        try {
            statusDiv.style.display = 'block';

            // Check if browser supports HLS natively (Safari)
            if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = url;
                statusDiv.innerHTML = '<small>HLS natif</small>';
            } else {
                // Use HLS.js for other browsers
                const Hls = await this.loadHlsJs();

                if (Hls.isSupported()) {
                    this.cleanupHls();
                    this.hlsInstance = new Hls({
                        debug: false,
                        enableWorker: true
                    });

                    this.hlsInstance.loadSource(url);
                    this.hlsInstance.attachMedia(video);

                    this.hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                        statusDiv.innerHTML = '<small>✅ HLS prêt</small>';
                        setTimeout(() => {
                            statusDiv.style.display = 'none';
                        }, 2000);
                    });

                    this.hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                        console.error('HLS Display Error:', data);
                        statusDiv.innerHTML = '<small>❌ Erreur HLS</small>';
                    });

                } else {
                    throw new Error('HLS not supported');
                }
            }

        } catch (error) {
            console.error('Error initializing HLS display:', error);
            statusDiv.innerHTML = '<small>❌ HLS indisponible</small>';
        }
    }

    save() {
        return this.data;
    }

    // Cleanup when block is destroyed
    destroy() {
        this.cleanupHls();
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