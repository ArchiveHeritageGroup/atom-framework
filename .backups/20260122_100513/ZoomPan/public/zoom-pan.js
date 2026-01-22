/**
 * ZoomPanViewer – Pure JS, plugin-free
 * Supports:
 *  - Image pan + zoom
 *  - Rotate
 *  - Reset
 *  - Fullscreen
 *  - Optional PDF viewer (PDF.js)
 */

class ZoomPanViewer {

    constructor(selector, options = {}) {
        this.container = document.querySelector(selector);
        if (!this.container) return;

        this.options = Object.assign({
            mimeType: "image/jpeg",
            height: 600,
        }, options);

        this.stage = this.container.querySelector(".zoom-pan-stage");
        this.img = this.stage.querySelector("img");

        this.scale = 1;
        this.rotation = 0;
        this.dragging = false;
        this.lastX = 0;
        this.lastY = 0;

        if (this.options.mimeType.includes("pdf")) {
            this.initializePdfViewer();
        } else {
            this.initializeImageViewer();
        }

        this.bindToolbar();
    }

    //----------------------------------------------------------
    // IMAGE VIEWER
    //----------------------------------------------------------
    initializeImageViewer() {
        this.img.style.transformOrigin = "center center";

        this.stage.addEventListener("mousedown", (e) => {
            this.dragging = true;
            this.lastX = e.clientX;
            this.lastY = e.clientY;
        });

        window.addEventListener("mouseup", () => this.dragging = false);

        window.addEventListener("mousemove", (e) => {
            if (!this.dragging) return;
            const dx = e.clientX - this.lastX;
            const dy = e.clientY - this.lastY;
            this.lastX = e.clientX;
            this.lastY = e.clientY;
            this.stage.scrollLeft -= dx;
            this.stage.scrollTop -= dy;
        });

        this.stage.addEventListener("wheel", (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? 0.1 : -0.1;
            this.scale = Math.min(5, Math.max(0.2, this.scale - delta));
            this.updateTransform();
        });
    }

    //----------------------------------------------------------
    // PDF VIEWER
    //----------------------------------------------------------
    async initializePdfViewer() {
        const pdfUrl = this.img.src;

        // Replace image element with canvas
        this.stage.innerHTML = "<canvas class='zoom-pan-pdfcanvas'></canvas>";
        this.canvas = this.stage.querySelector("canvas");
        this.ctx = this.canvas.getContext("2d");

        if (!window.pdfjsLib) {
            console.error("PDF.js not loaded!");
            return;
        }

        const pdf = await window.pdfjsLib.getDocument(pdfUrl).promise;
        this.pdf = pdf;
        this.currentPage = 1;

        await this.renderPdfPage(this.currentPage);
    }

    async renderPdfPage(num) {
        const page = await this.pdf.getPage(num);
        const viewport = page.getViewport({ scale: this.scale });

        this.canvas.width = viewport.width;
        this.canvas.height = viewport.height;

        await page.render({
            canvasContext: this.ctx,
            viewport: viewport
        }).promise;
    }

    //----------------------------------------------------------
    // Toolbar Actions
    //----------------------------------------------------------
    bindToolbar() {
        const buttons = this.container.querySelectorAll(".zoom-pan-toolbar button");
        buttons.forEach(btn => {
            btn.addEventListener("click", () => {
                const action = btn.dataset.action;
                this[action]?.();
            });
        });
    }

    "zoom-in"() {
        this.scale = Math.min(5, this.scale + 0.2);
        this.updateTransform();
    }

    "zoom-out"() {
        this.scale = Math.max(0.2, this.scale - 0.2);
        this.updateTransform();
    }

    "rotate-left"() {
        this.rotation -= 90;
        this.updateTransform();
    }

    "rotate-right"() {
        this.rotation += 90;
        this.updateTransform();
    }

    "reset"() {
        this.scale = 1;
        this.rotation = 0;
        this.updateTransform();
    }

    "fullscreen"() {
        if (this.container.requestFullscreen)
            this.container.requestFullscreen();
    }

    updateTransform() {
        if (this.options.mimeType.includes("pdf")) {
            this.renderPdfPage(this.currentPage);
            return;
        }

        this.img.style.transform =
            `scale(${this.scale}) rotate(${this.rotation}deg)`;
    }
}
