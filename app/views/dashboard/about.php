<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex align-items-center mb-4">
            <a href="<?= url('dashboard') ?>" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--kaldis-primary);">About Telegram Bot Monitoring &amp; Technical Limitations</h3>
                <div class="text-muted small">Understanding Telegram Bot API boundaries and operational rules</div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold text-dark">
                    <i class="bi bi-shield-exclamation text-warning me-2"></i> Telegram Bot API Architectural Boundaries
                </h5>
            </div>
            <div class="card-body p-4">
                <p class="lead fs-6 text-secondary">
                    The Kaldis Telegram Reply Tracker operates strictly within official Telegram Bot API constraints. For transparency and audit accuracy, system administrators and managers must understand these fundamental architectural limitations:
                </p>

                <div class="list-group list-group-flush mt-3">
                    <div class="list-group-item py-3">
                        <div class="d-flex">
                            <span class="badge bg-secondary me-3 align-self-start mt-1">1</span>
                            <div>
                                <h6 class="fw-bold mb-1">No Historical Message Visibility</h6>
                                <p class="text-muted small mb-0">
                                    A Telegram bot receives updates strictly in real-time via webhook. It <strong>cannot</strong> read messages sent in any group before the moment the bot was added.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="list-group-item py-3">
                        <div class="d-flex">
                            <span class="badge bg-secondary me-3 align-self-start mt-1">2</span>
                            <div>
                                <h6 class="fw-bold mb-1">Membership Boundary</h6>
                                <p class="text-muted small mb-0">
                                    The bot cannot monitor or access any group, supergroup, or channel where it is not currently an active member.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="list-group-item py-3">
                        <div class="d-flex">
                            <span class="badge bg-secondary me-3 align-self-start mt-1">3</span>
                            <div>
                                <h6 class="fw-bold mb-1">No Native Full Roster Listing API</h6>
                                <p class="text-muted small mb-0">
                                    Telegram Bot API does <em>not</em> provide an endpoint to list all participants of a group. Telegram only provides <code>getChatMember</code> (by numeric ID), <code>getChatAdministrators</code>, and <code>getChatMemberCount</code>. The group member roster therefore originates from <strong>authorized CSV import</strong> plus members seen sending messages in the group.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="list-group-item py-3">
                        <div class="d-flex">
                            <span class="badge bg-secondary me-3 align-self-start mt-1">4</span>
                            <div>
                                <h6 class="fw-bold mb-1">Join &amp; Leave Event Tracking Requires Administrator Status</h6>
                                <p class="text-muted small mb-0">
                                    Receiving <code>chat_member</code> updates when members join or leave requires the bot to be promoted to group Administrator and have <code>chat_member</code> enabled in <code>allowed_updates</code>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="list-group-item py-3">
                        <div class="d-flex">
                            <span class="badge bg-secondary me-3 align-self-start mt-1">5</span>
                            <div>
                                <h6 class="fw-bold mb-1">Message Deletions Are Not Disclosed to Bots</h6>
                                <p class="text-muted small mb-0">
                                    Telegram does not dispatch webhook events when a member deletes a message. If a reply is delivered and recorded by the tracker and subsequently deleted in Telegram, the recorded reply remains in the tracker database.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="list-group-item py-3">
                        <div class="d-flex">
                            <span class="badge bg-secondary me-3 align-self-start mt-1">6</span>
                            <div>
                                <h6 class="fw-bold mb-1">Bot-to-Bot Messages Are Suppressed</h6>
                                <p class="text-muted small mb-0">
                                    Telegram Bot API never delivers messages originating from other bots. Automated bot replies or bot notifications in the group will not be tracked or processed.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="list-group-item py-3">
                        <div class="d-flex">
                            <span class="badge bg-danger me-3 align-self-start mt-1">7</span>
                            <div>
                                <h6 class="fw-bold mb-1 text-danger">Anonymous Administrator Messages Cannot Be Tracked</h6>
                                <p class="text-muted small mb-0">
                                    If an administrator posts with <strong>"Send as Anonymous"</strong> enabled, Telegram replaces the sender identity with the group entity or <code>GroupAnonymousBot</code>. The tracker cannot attribute the message to a specific Telegram user ID and will <strong>not</strong> track the message. The system logs an audit warning if <code>#track</code> or <code>/track</code> is detected from an anonymous sender.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="list-group-item py-3">
                        <div class="d-flex">
                            <span class="badge bg-warning text-dark me-3 align-self-start mt-1">8</span>
                            <div>
                                <h6 class="fw-bold mb-1">Group Privacy Mode &amp; Re-addition Requirement</h6>
                                <p class="text-muted small mb-0">
                                    By default, Telegram bots have Privacy Mode enabled (they only see messages starting with <code>/</code> or direct mentions). When you disable Privacy Mode in BotFather (<code>/setprivacy -&gt; Disable</code>), the change <strong>only takes effect for groups joined AFTER the change</strong>. If the bot was already in a group, you must remove the bot and re-add it for it to see normal messages.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="list-group-item py-3">
                        <div class="d-flex">
                            <span class="badge bg-secondary me-3 align-self-start mt-1">9</span>
                            <div>
                                <h6 class="fw-bold mb-1">No Monitoring of Personal Account Outside Registered Groups</h6>
                                <p class="text-muted small mb-0">
                                    A standard Telegram bot cannot read private messages sent between personal accounts or access personal account activity outside groups where the bot is an active member. The tracker only detects your messages sent inside registered group chats.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 text-center">
                <h6 class="fw-bold mb-2">Need to verify bot connectivity for a group?</h6>
                <p class="text-muted small mb-3">Visit the Groups page to run real-time Bot API health checks, verify administrator permissions, and inspect members.</p>
                <a href="<?= url('groups') ?>" class="btn btn-kaldis shadow-sm">
                    <i class="bi bi-people me-1"></i> Go to Groups Management
                </a>
            </div>
        </div>
    </div>
</div>
