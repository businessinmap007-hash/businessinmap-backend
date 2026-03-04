$.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});
// var config = {
//     apiKey: "AIzaSyCM-fbnQsAPDqCOOV7Hx8Hh6Ld60dKrKWY",
//     authDomain: "oiltrips-75a50.firebaseapp.com",
//     databaseURL: "https://oiltrips-75a50.firebaseio.com",
//     projectId: "oiltrips-75a50",
//     storageBucket: "oiltrips-75a50.appspot.com",
//     messagingSenderId: "633540391277"
//   };




// Your web app's Firebase configuration
var firebaseConfig = {
    apiKey: "AIzaSyAJ5HI59IDVYjkg9AvMTWZNYq1XG6fJ4oU",
    authDomain: "arkabmaana-b0e04.firebaseapp.com",
    databaseURL: "https://arkabmaana-b0e04.firebaseio.com",
    projectId: "arkabmaana-b0e04",
    storageBucket: "",
    messagingSenderId: "753177837943",
    appId: "1:753177837943:web:a3f70a5538bc2283"
};
// Initialize Firebase
firebase.initializeApp(firebaseConfig);

  
// firebase.initializeApp(config);
const messaging = firebase.messaging();
// messaging.usePublicVapidKey("BPEnbkfg9j7_-uRYg92rcfMdJlNBxNoOSA_jbCn6XdWSs_kHIHFVriXioaOK00EnjGyvIhjkCuurIi6fSJWGutg");
messaging.usePublicVapidKey("BLY1f4nBJyAVcAvCljwSnKvLLHKgvN_2pe7JyvEe729IQbSbvVMgQFR3i2JUqWf6Awc0PZPeh5XhQK8P5gm6kso");
messaging.requestPermission().then(function () {
    // console.log('Notification permission granted.');
    getToken();
}).catch(function (err) {
    console.log('Unable to get permission to notify.', err);
});

function getToken() {
    messaging.onTokenRefresh(function () {
        messaging.getToken().then(function (refreshedToken) {
            // console.log(refreshedToken);
            Listen();
        }).catch(function (err) {
            console.log('Unable to retrieve refreshed token ', err);
            showToken('Unable to retrieve refreshed token ', err);
        });
    });
    messaging.getToken().then(function (currentToken) {
        if (currentToken) {
            // console.log(currentToken);
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
      //   console.log(payload.data.title);
      //   console.log(payload.data.body);

        //$(".notification-box").html("count");


        $(".noti-dot").removeAttr('style', true);

        
        var n = new Notification(payload.notification.title, {
			body: payload.notification.body,
			icon: payload.notification.icon, // optional
			onclick: payload.notification.click_action
		}); 
		
		
		
		n.onclick = function(event) {
                event.preventDefault(); // prevent the browser from focusing the Notification's tab
              window.open(payload.notification.click_action, '_blank');
            }
    });
}