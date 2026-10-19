importScripts('https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js');
 importScripts('https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js');
 const firebaseConfig = {apiKey:'AIzaSyAravivpxM8-uc_PTmb2reJ4bwGCc6J3Ik',
authDomain:'ebroker-wrteam.firebaseapp.com',
projectId:'ebroker-wrteam',
storageBucket:'ebroker-wrteam.appspot.com',
messagingSenderId:'63168540332',
appId:'1:63168540332:web:d183e9ca13866ec5623909',
measurementId:'G-W05KYC2K8P',
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