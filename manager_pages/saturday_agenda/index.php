<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saturday Agenda</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
    </script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>
    <script src="js/app.js" defer></script>
</head>
<body>
    <div class="dashboard-container">
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <div class="title-icon">
                        <i data-lucide="calendar-days" style="width:18px;height:18px;"></i>
                    </div>
                    <div>
                        <div class="title-text">Saturday Agenda</div>
                        <div class="title-sub">Plan and review weekend priorities.</div>
                    </div>
                </div>
            </header>

            <section class="content-grid">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <div class="panel-title">Agenda PDF</div>
                            <div class="panel-sub">Send the latest Saturday agenda to WhatsApp.</div>
                        </div>
                        <div class="panel-actions">
                            <button class="ghost-btn" id="viewArchiveBtn" type="button">
                                <i data-lucide="archive" style="width:16px;height:16px;"></i>
                                View Old Records
                            </button>
                            <button class="ghost-btn" id="previewBtn" type="button">
                                <i data-lucide="file-search" style="width:16px;height:16px;"></i>
                                Preview
                            </button>
                        </div>
                    </div>

                    <div class="upload-card" id="uploadCard">
                        <div class="upload-icon">
                            <i data-lucide="file-up" style="width:18px;height:18px;"></i>
                        </div>
                        <div>
                            <div class="upload-title">Upload agenda PDF</div>
                            <div class="upload-sub">Drop the PDF here or click to browse.</div>
                            <div class="upload-file" id="selectedFileName">No file selected</div>
                        </div>
                        <button class="light-btn" id="chooseFileBtn" type="button">Choose File</button>
                        <input id="agendaPdfInput" class="file-input-hidden" type="file" accept="application/pdf" />
                    </div>

                    <div class="template-card">
                        <div class="template-header">
                            <div>
                                <div class="panel-title">WhatsApp Message</div>
                                <div class="panel-sub">Fill the placeholders and preview the final message.</div>
                            </div>
                        </div>

                        <div class="field-row">
                            <label class="field">
                                <span class="field-label">Recipient name</span>
                                <input id="tplName" class="field-input" type="text" placeholder="User name" />
                            </label>
                            <label class="field">
                                <span class="field-label">Date</span>
                                <input id="tplDate" class="field-input" type="date" />
                            </label>
                        </div>

                        <div class="field-row">
                            <label class="field">
                                <span class="field-label">Meeting time</span>
                                <input id="tplTime" class="field-input" type="time" />
                            </label>
                            <label class="field">
                                <span class="field-label">Day</span>
                                <input id="tplDay" class="field-input" type="text" placeholder="Saturday" />
                            </label>
                        </div>

                        <div class="field-row">
                            <label class="field">
                                <span class="field-label">Reach office by</span>
                                <input id="tplReach" class="field-input" type="time" />
                            </label>
                            <label class="field">
                                <span class="field-label">Non-availability from</span>
                                <input id="tplFrom" class="field-input" type="time" />
                            </label>
                        </div>

                        <div class="field-row single">
                            <label class="field">
                                <span class="field-label">Non-availability to</span>
                                <input id="tplTo" class="field-input" type="time" />
                            </label>
                        </div>

                        <label class="field">
                            <span class="field-label">Final message</span>
                            <textarea id="tplPreview" class="field-input" rows="9" readonly></textarea>
                        </label>
                    </div>

                    <div class="cta-row">
                        <div class="status-pill" id="selectionStatus">0 selected</div>
                        <button class="primary-btn" id="sendSelectedBtn" type="button">
                            <i data-lucide="send" style="width:16px;height:16px;"></i>
                            Send to Selected
                        </button>
                    </div>

                    <div class="note-text">Bulk send will deliver to all selected active users.</div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <div class="panel-title">Active Users</div>
                            <div class="panel-sub">Select recipients for the WhatsApp message.</div>
                        </div>
                        <label class="switch-line">
                            <input type="checkbox" id="selectAllUsers" />
                            <span>Select all</span>
                        </label>
                    </div>

                    <div class="search-row">
                        <i data-lucide="search" style="width:16px;height:16px;"></i>
                        <input id="userSearch" type="text" placeholder="Search by name or department" />
                    </div>

                    <div id="userList" class="user-list">
                        <div class="empty-state">Loading users...</div>
                    </div>

                    <div class="user-actions">
                        <button class="ghost-btn" id="clearSelectionBtn" type="button">Clear</button>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div class="loader-overlay" id="sendLoader">
        <div class="loader-card">
            <div class="spinner"></div>
            <div class="loader-text">Sending WhatsApp messages...</div>
        </div>
    </div>

    <div class="modal-overlay" id="resultModal">
        <div class="modal">
            <div class="modal-header">
                <div>
                    <div class="modal-title" id="modalTitle">Send Status</div>
                    <div class="modal-sub" id="modalSubtitle"></div>
                </div>
                <button class="ghost-btn" id="closeModalBtn" type="button">Close</button>
            </div>
            <div class="modal-body">
                <div class="modal-message" id="modalMessage"></div>
                <div class="modal-list" id="modalList"></div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="archiveModal">
        <div class="modal">
            <div class="modal-header">
                <div>
                    <div class="modal-title">Agenda Archives</div>
                    <div class="modal-sub">Previous month PDFs saved from uploads.</div>
                </div>
                <button class="ghost-btn" id="closeArchiveBtn" type="button">Close</button>
            </div>
            <div class="modal-body">
                <div class="modal-message" id="archiveMessage">Loading archives...</div>
                <div class="archive-list" id="archiveList"></div>
            </div>
        </div>
    </div>
</body>
</html>
