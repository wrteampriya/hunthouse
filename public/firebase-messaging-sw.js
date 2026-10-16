importScripts('https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js');
 importScripts('https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js');
 const firebaseConfig = {apiKey:'AIzaSyAC3TNSk8Tyco6nK7XJsYRWclJ6W4_KW9w',
authDomain:'houshunt-by-mdev-team.firebaseapp.com',
projectId:'houshunt-by-mdev-team',
storageBucket:'houshunt-by-mdev-team.firebasestorage.app',
messagingSenderId:'277878783731',
appId:'1:277878783731:web:c1a96f309d8a5c106b0186',
measurementId:'G-ZX27H6G9DG',
 };
if (!firebase.apps.length) {
 firebase.initializeApp(firebaseConfig);
 }
const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function(payload) {
console.log(payload);
 var title = payload.data.title;
var options = {
body: payload.data.body,
icon: payload.data.icon,
data: {
 time: new Date(Date.now()).toString(),
 click_action: payload.data.click_action
 }
};
return self.registration.showNotification(title, options);
 });
self.addEventListener('notificationclick', function(event) {
 var action_click = event.notification.data.click_action;
event.notification.close();
event.waitUntil(
clients.openWindow(action_click)
 );
});