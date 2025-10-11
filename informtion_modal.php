<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Information Modal</title>
<style>
  /* ===== Base Styles ===== */
  body {
    font-family: "Poppins", sans-serif;
    background-color: #f5f6fa;
    margin: 0;
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  button {
    padding: 10px 20px;
    background: #111;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 15px;
    transition: 0.3s ease;
  }
  button:hover {
    background: #333;
  }

  /* ===== Overlay ===== */
  .overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(6px);
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
    z-index: 100;
  }

  .overlay.active {
    opacity: 1;
    pointer-events: all;
  }

  /* ===== Modal Box ===== */
  .modal {
    background: white;
    border-radius: 16px;
    padding: 30px;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    transform: translateY(40px);
    opacity: 0;
    transition: all 0.4s ease;
    position: relative;
  }

  .overlay.active .modal {
    transform: translateY(0);
    opacity: 1;
  }

  /* ===== Modal Content ===== */
  .modal h2 {
    font-size: 20px;
    margin-bottom: 10px;
    color: #111;
    text-align: center;
  }

  .modal p {
    font-size: 15px;
    color: #444;
    text-align: center;
    line-height: 1.6;
    margin-bottom: 20px;
  }

  /* ===== Close Button ===== */
  .close-btn {
    position: absolute;
    top: 12px;
    right: 14px;
    background: transparent;
    border: none;
    font-size: 20px;
    color: #888;
    cursor: pointer;
    transition: color 0.3s ease;
  }
  .close-btn:hover {
    color: #000;
  }

  /* ===== Responsive ===== */
  @media (max-width: 480px) {
    .modal {
      padding: 20px;
    }
    .modal h2 {
      font-size: 18px;
    }
    .modal p {
      font-size: 14px;
    }
  }
</style>
</head>
<body>

<!-- Button to trigger modal -->
<button id="openModal">Show Information</button>

<!-- Modal Overlay -->
<div class="overlay" id="infoOverlay">
  <div class="modal">
    <button class="close-btn" id="closeModal">&times;</button>
    <h2>Information</h2>
    <p>
      This is a short and clean information message displayed in a minimalistic modal.
      You can customize the content or add buttons easily.
    </p>
    <button id="okBtn">Got it</button>
  </div>
</div>

<script>
  const openBtn = document.getElementById("openModal");
  const closeBtn = document.getElementById("closeModal");
  const okBtn = document.getElementById("okBtn");
  const overlay = document.getElementById("infoOverlay");

  openBtn.addEventListener("click", () => overlay.classList.add("active"));
  closeBtn.addEventListener("click", () => overlay.classList.remove("active"));
  okBtn.addEventListener("click", () => overlay.classList.remove("active"));
  window.addEventListener("click", (e) => {
    if (e.target === overlay) overlay.classList.remove("active");
  });
</script>

</body>
</html>
