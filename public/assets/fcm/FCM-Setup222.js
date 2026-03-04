$.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});
var config = {
    apiKey: "AIzaSyDGAdzRX-G_A-Apr560xFKkUhftDC_y7so",
    authDomain: "otlbha-project.firebaseapp.com",
    databaseURL: "https://otlbha-project.firebaseio.com",
    projectId: "otlbha-project",
    storageBucket: "otlbha-project.appspot.com",
    messagingSenderId: "455185106819"
};
firebase.initializeApp(config);
const messaging = firebase.messaging();
messaging.usePublicVapidKey("BPXd_w4pQhb_6R8kZ2d7vF3fALsy91R4w3cbKYPsh_bcfkcB9-mrsg5_efq7WSoaWt3iUSflQMYBnSoJ44d-8_s");
messaging.requestPermission().then(function () {
    console.log('Notification permission granted.');
    getToken();
}).catch(function (err) {
    console.log('Unable to get permission to notify.', err);
});

function getToken() {
    messaging.onTokenRefresh(function () {
        messaging.getToken().then(function (refreshedToken) {
            console.log(refreshedToken);
            Listen();
        }).catch(function (err) {
            console.log('Unable to retrieve refreshed token ', err);
            showToken('Unable to retrieve refreshed token ', err);
        });
    });
    messaging.getToken().then(function (currentToken) {
        if (currentToken) {
            console.log(currentToken);
            if (userId) {
                $.ajax({
                    type: "POST", url: url, data: {id: userId, token: currentToken}, success: function (data) {
                    }
                });
            }
            Listen();
        } else {
            console.log('No Instance ID token available. Request permission to generate one.');
        }
    }).catch(function (err) {
        console.log('An error occurred while retrieving token. ', err);
    });

}

function Listen() {
    messaging.onMessage(function (payload) {
        console.log(payload);

        const notificationTitle = payload.data.message;
        const notificationOptions = {
            body:  payload.data.description,
            icon: '/firebase-logo.png'
        };

        return self.registration.showNotification(notificationTitle,notificationOptions);

    });
}