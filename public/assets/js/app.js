// Marketplace Laundry Client App JavaScript
document.addEventListener('DOMContentLoaded', () => {
    console.log('Marketplace Laundry App initialized successfully.');

    // Auto-dismiss alert notifications after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });
});
