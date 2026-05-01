<!-- SUCCESS MODAL -->
<div class="modal-overlay" id="successModal">
  <div class="modal">
    <div class="modal-icon">🎉</div>
    <h3>Order Placed!</h3>
    <p id="successMsg">We received your order. We'll contact you shortly.</p>
    <button class="modal-close" onclick="resetAll()">OK</button>
  </div>
</div>

<!-- PDF VIEWER MODAL -->
<div class="pdf-overlay" id="pdfOverlay" onclick="if(event.target===this)closePdf()">
  <div class="pdf-topbar">
    <div>
      <div class="pdf-topbar-title" id="pdfTitle">Sample PDF</div>
      <div class="pdf-topbar-sub">Read and download for free</div>
    </div>
    <a id="pdfDownloadBtn" href="#" download class="pdf-action-btn pdf-dl-btn">
      <i class="fas fa-download"></i> Download
    </a>
    <button class="pdf-action-btn pdf-close-btn" onclick="closePdf()">✕</button>
  </div>
  <div class="pdf-iframe-wrap">
    <iframe id="pdfIframe" src="" title="Sample PDF"></iframe>
  </div>
  <div class="pdf-mobile-card">
    <div class="pdf-mobile-inner">
      <div class="pdf-mobile-icon">📄</div>
      <div class="pdf-mobile-title" id="pdfMobileTitle">Sample PDF</div>
      <div class="pdf-mobile-sub">Download the sample PDF to read and decide.</div>
      <a id="pdfMobileDl" href="#" download class="pdf-mobile-dl">
        <i class="fas fa-download"></i> Download &amp; Read
      </a>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- LOADING -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner"></div>
</div>
