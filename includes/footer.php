</div>
        </main>
    </div>

    <script>
        // Load global notification handling
        document.addEventListener('DOMContentLoaded', function() {
            // Prevent double initialization
            if (!window.notificationsManager) {
                const notificationsScript = document.createElement('script');
                notificationsScript.src = '/assets/js/notifications.js';
                notificationsScript.onload = function() {
                    if (typeof NotificationsManager === 'function' && !window.notificationsManager) {
                        window.notificationsManager = new NotificationsManager();
                    }
                };
                document.body.appendChild(notificationsScript);
            }
            
            // Initialize real-time notification polling if user is logged in
            <?php if (isAuthenticated()): ?>
            fetch('/api/get-unread-count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        const event = new CustomEvent('notifications-updated', { detail: { count: data.count } });
                        document.dispatchEvent(event);
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
                
            // Poll every 30 seconds
            setInterval(() => {
                fetch('/api/get-unread-count.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.count > 0) {
                            const event = new CustomEvent('notifications-updated', { detail: { count: data.count } });
                            document.dispatchEvent(event);
                        }
                    })
                    .catch(error => console.error('Error fetching notifications:', error));
            }, 30000);
            <?php endif; ?>
        });
    </script>
    <!-- Choices.js JS -->
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
</body>
</html>
