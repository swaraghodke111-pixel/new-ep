    </div><!-- .page-content -->
    
    <!-- Site Footer -->
    <footer class="site-footer">
        <a onclick="openAboutModal()">About Us</a>
        <a onclick="openHelpModal()">Help</a>
    </footer>
</div><!-- .main-wrapper -->

<!-- About Us Modal -->
<div class="modal-backdrop" id="aboutModal" onclick="closeAboutModalOnOutsideClick(event)">
    <div class="modal-container">
        <div class="modal-header">
            <h3>About Us</h3>
            <button class="modal-close-btn" onclick="closeAboutModal()">&times;</button>
        </div>
        <div class="modal-body">
            <h4>Portal Overview</h4>
            <p>The Online Examination Portal is an enterprise-grade platform designed to conduct secure, timed, and role-based examinations and coding evaluations.</p>
            
            <h4>Purpose</h4>
            <p>To provide a robust academic assessment framework allowing students, faculty, and administrators to seamlessly schedule, execute, and monitor quizzes and programming challenges.</p>
            
            <h4>Institute Information</h4>
            <p>Powered by the Advanced Institute of Technology, Academic Assessment Division.</p>
            
            <h4>Project Objectives</h4>
            <p>Streamline exam scheduling, verify coding submissions automatically, and support instant feedback and results reporting.</p>
            
            <h4>Contact Information</h4>
            <p>Email: info@examportal.edu<br>Phone: +1 (555) 019-2834</p>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal-backdrop" id="helpModal" onclick="closeHelpModalOnOutsideClick(event)">
    <div class="modal-container" style="max-width: 680px;">
        <div class="modal-header">
            <h3>Help & Support</h3>
            <button class="modal-close-btn" onclick="closeHelpModal()">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
            
            <?php if (is_logged_in() && ($_SESSION['user_role'] ?? $_SESSION['role'] ?? '') === 'admin'):
                // ── SUPER ADMIN VIEW: REPLY TO USER QUERIES ─────────────────────────
                $all_queries = get_help_queries();
            ?>
                <div style="background: rgba(166, 124, 82, 0.08); border: 1.5px solid #A67C52; padding: 18px; border-radius: 12px; margin-bottom: 24px;">
                    <h4 style="margin-bottom: 6px; color: #7A5C48; display: flex; align-items: center; justify-content: space-between;">
                        <span>💬 User Help Queries & Super Admin Reply Panel</span>
                        <span class="badge" style="background:#A67C52; color:#fff; font-size:0.75rem;"><?= count($all_queries) ?> Queries</span>
                    </h4>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 16px;">
                        As Super Admin, view user help requests below and send direct replies to users.
                    </p>

                    <?php if ($all_queries): ?>
                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            <?php foreach ($all_queries as $hq): ?>
                            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 14px;" id="query-card-<?= $hq['id'] ?>">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                    <div>
                                        <strong style="font-size: 0.9rem; color: var(--text-main);"><?= h($hq['user_name']) ?></strong>
                                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-left: 6px;">(<?= h($hq['user_email']) ?>)</span>
                                    </div>
                                    <span class="badge <?= $hq['status'] === 'replied' ? 'badge-passed' : 'badge-scheduled' ?>" style="font-size: 0.72rem;">
                                        <?= $hq['status'] === 'replied' ? '✅ Replied' : '🟡 Pending Reply' ?>
                                    </span>
                                </div>

                                <div style="font-size: 0.85rem; color: var(--text-main); margin-bottom: 10px; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 6px; border-left: 3px solid #A67C52;">
                                    "<?= h($hq['query_text']) ?>"
                                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; text-align: right;">
                                        📅 <?= format_datetime($hq['created_at']) ?>
                                    </div>
                                </div>

                                <?php if ($hq['status'] === 'replied'): ?>
                                    <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 10px; margin-top: 8px; margin-bottom:10px;">
                                        <div style="font-size: 0.8rem; font-weight: 700; color: var(--green); margin-bottom: 4px;">💬 Your Reply (Super Admin):</div>
                                        <div style="font-size: 0.85rem; color: var(--text-main);"><?= h($hq['admin_reply']) ?></div>
                                        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; text-align: right;">
                                            Replied on <?= format_datetime($hq['replied_at']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Reply Input Form for Super Admin -->
                                <div id="reply-form-wrap-<?= $hq['id'] ?>">
                                    <div style="display: flex; gap: 8px;">
                                        <input type="text" id="reply-input-<?= $hq['id'] ?>" class="form-control"
                                               placeholder="<?= $hq['status'] === 'replied' ? 'Update your reply...' : 'Type your reply to this user...' ?>"
                                               value="<?= h($hq['admin_reply'] ?? '') ?>"
                                               style="flex: 1; font-size: 0.82rem;">
                                        <button type="button" class="btn btn-primary" onclick="sendAdminReply(<?= $hq['id'] ?>)"
                                                style="background: #A67C52; border-color: #A67C52; font-size: 0.82rem; white-space: nowrap;">
                                            <?= $hq['status'] === 'replied' ? 'Update Reply ✏️' : 'Send Reply 🚀' ?>
                                        </button>
                                    </div>
                                    <div id="reply-status-<?= $hq['id'] ?>" style="margin-top: 6px; font-size: 0.78rem; display: none;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 12px;">
                            No user help queries submitted yet.
                        </div>
                    <?php endif; ?>
                </div>

            <?php else:
                // ── USER / FACULTY VIEW: SUBMIT QUERY & VIEW ADMIN REPLIES ──────────
                $user_queries = is_logged_in() ? get_help_queries((int)$_SESSION['user_id']) : [];
            ?>
                <!-- Ask Super Admin for Help Box -->
                <div style="background: rgba(166, 124, 82, 0.08); border: 1.5px solid #A67C52; padding: 18px; border-radius: 12px; margin-bottom: 24px;">
                    <h4 style="margin-bottom: 6px; color: #7A5C48; display: flex; align-items: center; gap: 8px;">
                        💬 Ask Super Admin for Help
                    </h4>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 12px;">
                        Have a question or issue? Type your query below to send a direct help request notification to the Super Admin.
                    </p>
                    <form id="help-request-form" onsubmit="submitHelpRequest(event)">
                        <?php if (!is_logged_in()): ?>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <input type="text" id="help-user-name" class="form-control" placeholder="Your Name" required style="font-size: 0.85rem;">
                                <input type="email" id="help-user-email" class="form-control" placeholder="Your Email Address" required style="font-size: 0.85rem;">
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="help-query-input" class="form-control" placeholder="Type your help question or query..." required style="flex: 1; font-size: 0.85rem;">
                            <button type="submit" class="btn btn-primary" id="help-submit-btn" style="background: #A67C52; border-color: #A67C52; white-space: nowrap; font-size: 0.85rem;">
                                Send Request 🚀
                            </button>
                        </div>
                    </form>
                    <div id="help-form-status" style="margin-top: 10px; display: none; font-size: 0.85rem; padding: 8px 12px; border-radius: 8px;"></div>
                </div>

                <?php if ($user_queries): ?>
                <!-- User's Previous Queries & Admin Replies Section -->
                <div style="background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 12px; padding: 18px; margin-bottom: 24px;">
                    <h4 style="margin-bottom: 10px; color: var(--purple-3);">📋 Your Help Queries & Super Admin Replies</h4>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($user_queries as $uq): ?>
                        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <span style="font-weight: 600; font-size: 0.85rem;">❓ Your Query:</span>
                                <span class="badge <?= $uq['status'] === 'replied' ? 'badge-passed' : 'badge-scheduled' ?>" style="font-size: 0.7rem;">
                                    <?= $uq['status'] === 'replied' ? '✅ Replied' : '🟡 Pending Reply' ?>
                                </span>
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-main); margin-bottom: 6px;">"<?= h($uq['query_text']) ?>"</div>
                            
                            <?php if ($uq['status'] === 'replied'): ?>
                                <div style="background: rgba(16, 185, 129, 0.1); border-left: 3px solid #10b981; padding: 8px 12px; border-radius: 4px; margin-top: 8px;">
                                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--green);">👑 Super Admin Reply:</div>
                                    <div style="font-size: 0.85rem; color: var(--text-main); margin-top: 2px;"><?= h($uq['admin_reply']) ?></div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; text-align: right;">
                                        Replied on <?= format_datetime($uq['replied_at']) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <h4>Login Help</h4>
            <p>Select your role card (Student or Admin/Faculty), fill in your registered email and password, and click Sign In.</p>
            
            <h4>Password Reset Guide</h4>
            <p>Click "Forgot password?" on the login page, enter your registered email address, and use the demo link generated to set a new password.</p>
            
            <h4>Examination Instructions</h4>
            <p>Ensure you have a stable internet connection. Do not refresh or navigate away from the active quiz page, as the timer runs continuously in real-time and cannot be paused.</p>
            
            <h4>Coding Examination Help</h4>
            <p>Select your programming language from the dropdown menu, write your code, and click "Run Code" to compile. Click "Submit Solution" to submit against all tests.</p>
            
            <h4>Quiz Instructions</h4>
            <p>Select your answers for MCQs. Use the navigator grid to track which questions have been answered. Click "Submit Exam" when done.</p>
            
            <h4>Technical Support</h4>
            <p>Email: support@examportal.edu<br>Phone: +1 (555) 019-2835</p>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.querySelector('.main-wrapper').classList.toggle('expanded');
}
function toggleNotifications() {
    const panel = document.getElementById('notifPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
function toggleProfileDropdown(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('profileDropdown');
    dropdown.classList.toggle('show');
}
document.addEventListener('click', function(e) {
    const notifPanel = document.getElementById('notifPanel');
    if (notifPanel && !notifPanel.contains(e.target) && !e.target.closest('.notif-btn')) {
        notifPanel.style.display = 'none';
    }
    
    const profileDropdown = document.getElementById('profileDropdown');
    if (profileDropdown && !profileDropdown.contains(e.target) && !e.target.closest('.profile-toggle')) {
        profileDropdown.classList.remove('show');
    }
});

/* Redesigned Modal Control Functions */
function openAboutModal() {
    document.getElementById('aboutModal').classList.add('show');
}
function closeAboutModal() {
    document.getElementById('aboutModal').classList.remove('show');
}
function closeAboutModalOnOutsideClick(e) {
    if (e.target.id === 'aboutModal') {
        closeAboutModal();
    }
}
function openHelpModal() {
    document.getElementById('helpModal').classList.add('show');
}
function closeHelpModal() {
    document.getElementById('helpModal').classList.remove('show');
}
function closeHelpModalOnOutsideClick(e) {
    if (e.target.id === 'helpModal') {
        closeHelpModal();
    }
}

async function submitHelpRequest(e) {
    e.preventDefault();
    const queryInput = document.getElementById('help-query-input');
    const nameInput = document.getElementById('help-user-name');
    const emailInput = document.getElementById('help-user-email');
    const submitBtn = document.getElementById('help-submit-btn');
    const statusDiv = document.getElementById('help-form-status');

    if (!queryInput || !queryInput.value.trim()) return;

    submitBtn.disabled = true;
    submitBtn.innerText = 'Sending...';

    const formData = new FormData();
    formData.append('query', queryInput.value.trim());
    if (nameInput) formData.append('name', nameInput.value.trim());
    if (emailInput) formData.append('email', emailInput.value.trim());

    try {
        const response = await fetch('<?= BASE_URL ?>/includes/send_help.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        statusDiv.style.display = 'block';
        if (data.success) {
            statusDiv.className = 'alert alert-success';
            statusDiv.style.background = 'rgba(16,185,129,0.15)';
            statusDiv.style.color = 'var(--green)';
            statusDiv.style.border = '1px solid #10b981';
            statusDiv.innerHTML = '✅ ' + data.message;
            queryInput.value = '';
            if (nameInput) nameInput.value = '';
            if (emailInput) emailInput.value = '';
            setTimeout(() => location.reload(), 1000);
        } else {
            statusDiv.className = 'alert alert-error';
            statusDiv.style.background = 'rgba(239,68,68,0.15)';
            statusDiv.style.color = 'var(--red)';
            statusDiv.style.border = '1px solid #ef4444';
            statusDiv.innerHTML = '❌ ' + (data.message || 'Failed to send request.');
        }
    } catch (err) {
        statusDiv.style.display = 'block';
        statusDiv.className = 'alert alert-error';
        statusDiv.style.background = 'rgba(239,68,68,0.15)';
        statusDiv.style.color = 'var(--red)';
        statusDiv.style.border = '1px solid #ef4444';
        statusDiv.innerHTML = '❌ An error occurred while sending your request.';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Send Request 🚀';
    }
}

async function sendAdminReply(queryId) {
    const replyInput = document.getElementById('reply-input-' + queryId);
    const statusDiv  = document.getElementById('reply-status-' + queryId);
    if (!replyInput || !replyInput.value.trim()) return;

    const formData = new FormData();
    formData.append('query_id', queryId);
    formData.append('reply_text', replyInput.value.trim());

    try {
        const res = await fetch('<?= BASE_URL ?>/includes/reply_help.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (statusDiv) {
            statusDiv.style.display = 'block';
            if (data.success) {
                statusDiv.style.color = 'var(--green)';
                statusDiv.innerText = '✅ ' + data.message;
                setTimeout(() => location.reload(), 800);
            } else {
                statusDiv.style.color = 'var(--red)';
                statusDiv.innerText = '❌ ' + (data.message || 'Error submitting reply.');
            }
        }
    } catch(err) {
        if (statusDiv) {
            statusDiv.style.display = 'block';
            statusDiv.style.color = 'var(--red)';
            statusDiv.innerText = '❌ Network error submitting reply.';
        }
    }
}
</script>
</body>
</html>
