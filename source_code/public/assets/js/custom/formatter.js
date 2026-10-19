function imageFormatter(value, row) {
    if (value) {
        var svg_clr_setting = row.svg_clr;
        if (svg_clr_setting != null && svg_clr_setting == 1) {
            var imageUrl = value;
            if (value) {
                if (imageUrl.split('.').pop() === 'svg') {
                    return '<embed class="svg-img" src="' + value + '">';
                } else {
                    return '<a class="image-popup-no-margins" href="' + value + '"><img class="rounded avatar-md shadow img-fluid" alt="" src="' + value + '" width="55"></a>';
                }
            }
        } else {
            return (value !== '') ? '<a class="image-popup-no-margins" href="' + value + '"><img class="rounded avatar-md shadow img-fluid" alt="" src="' + value + '" width="55"></a>' : '';
        }
    }
    return null;

}

function sub_category(value, row) {
    return '<a href="get_sub_categories/' + row.id + '"> <div class="category_count">' + value + ' Sub Categories</div></a>';
}

function custom_fields(value, row) {

    var rootUrl = window.location.protocol + '//' + window.location.host;
    return '<a href="' + rootUrl + '/category_custom_fields/' + row.id + '"> <div class="category_count">' + value + ' Custom Fields</div></a>';

}


function premium_status_switch(value, row) {
    var status;
    if (row.added_by == 'Admin') {
        status = value == "1" ? "checked" : "";
        return `<div class="form-check form-switch" style="padding-left: 5.2rem;">
                    <input class = "form-check-input switch1" id = "${row.id}" onclick = "chk(this);" data-url="updateaccessability" type = "checkbox" role = "switch"${status} value = ${value}>
                </div>`;
    } else {
        status = value == "1" ? "checked" : "";
        return `<div class="form-check form-switch" style="padding-left: 5.2rem;">
                    <input disabled class = "form-check-input switch1" id = "${row.id}" type = "checkbox" role = "switch"${status} value = ${value}>
                </div>`;
    }
}


function badge(value, row) {
    if (value == "review") {
        badgClass = 'primary';
        badgeText = 'Under Review';
    }
    if (value == "approve") {
        badgClass = 'success';
        badgeText = 'Approved';
    }
    if (value == "reject") {
        badgClass = 'danger';
        badgeText = 'Rejected';
    }
    return '<span class="badge rounded-pill bg-' + badgClass +
        '">' + badgeText + '</span>';
}





function propertyTypeFormatter(value, row) {
    if (row.property_type == 0) {
        badgClass = 'primary';
        badgeText = (window.trans["Sell"] || "Sell");
    }
    if (row.property_type == 1) {
        badgClass = 'secondary';
        badgeText = (window.trans["Rent"] || "Rent");
    }
    if (row.property_type == 2) {
        badgClass = 'info';
        badgeText = (window.trans["Sold"] || "Sold");
    }
    if (row.property_type == 3) {
        badgClass = 'info';
        badgeText = (window.trans["Rented"] || "Rented");
    }
    return '<span class="badge rounded-pill bg-' + badgClass +
        '">' + badgeText + '</span>';
}



function status_badge(value, row) {
    if (value == '0') {
        badgClass = 'danger';
        badgeText = 'OFF';
    } else {
        badgClass = 'success';
        badgeText = 'ON';
    }
    return '<span class="badge rounded-pill bg-' + badgClass +
        '">' + badgeText + '</span>';
}

function user_status_badge(value, row) {
    if (value == '0') {
        badgClass = 'danger';
        badgeText = 'Inacive';
    } else {
        badgClass = 'success';
        badgeText = 'Active';
    }
    return '<span class="badge rounded-pill bg-' + badgClass +
        '">' + badgeText + '</span>';
}

function style_app(value, row) {
    return '<a class="image-popup-no-margins" href="images/app_styles/' + value + '.png"><img src="images/app_styles/' + value + '.png" alt="style_4"  height="60" width="60" class="rounded avatar-md shadow img-fluid"></a>';
}

function filters(value) {


    if (value == "most_liked") {

        filter = "Most Liked";
    } else if (value == "price_criteria") {
        filter = "Price Criteria";
    } else if (value == "category_criteria") {
        filter = "Category Criteria";
    } else if (value == "most_viewed") {
        filter = "Most Viewed";
    }
    return filter;
}

function adminFile(value, row) {
    return "<a href='languages/" + row.code + ".json ' )+' > View File < /a>";

}

function appFile(value, row) {
    return "<a href='lang/" + row.code + ".json ' )+' > View File < /a>";
}

function enableDisableFeaturedPropertiesFormatter(value, row) {
    let status = row.status == '1' ? 'checked' : '';
    return `<div class="form-check form-switch center" style="margin-top: 10%;padding-left: 5.2rem;">
                <input class="form-check-input switch1" id="${row.id}"  onclick="chk(this);" type="checkbox" role="switch" ${status} '>
            </div>`
}

function featuredPropertiesDataFormatter(value, row) {
    return `<div class="featured_property">
                <div class="image-container">
                    <img src="${row.title_image}" alt="Image">
                    <div class="featured-property-type"> ${(window.trans[row.type] || row.type)} </div>
                </div>
            <div>
            <div class="d-flex">
                <img src="${row.category.image}" alt="Image" height="24px" width="24px">
                <div class="category"> ${row.category.category} </div>
            </div>
            <div class="title"> ${row.title} </div>
            <div class="price"> ${row.price} </div>
            <div class="city">
                <i class="bi bi-geo-alt"></i>
                ${row.city}
            </div>`;
}

function enableDisableSwitchFormatter(value, row) {
    if (row.edit_status_url != null && value != null) {
        let status = (value == "1" || value == "active") ? "checked" : "";
        let disabled = row.is_disabled ? 'disabled' : '';
        return `<div class="form-check form-switch" text-center style="display: flex; justify-content: center;">
                <input class = "form-check-input switch1"id = "${row.id}" onclick = "chk(this);" data-url="${row.edit_status_url}" type="checkbox" role="switch" ${status} value="${value}" ${disabled}>
            </div>`;
    }
    return null;
}

function yesNoStatusFormatter(value) {
    let text = "";
    let classType = "";
    if (value == 1) {
        text = (window.trans["Yes"] || "Yes");
        classType = 'success'
    } else {
        text = (window.trans["No"] || "No");
        classType = 'danger'
    }
    return '<span class="badge rounded-pill bg-' + classType + '">' + text + '</span>';
}

function enableDisableCityImageSwitchFormatter(value, row) {
    let disabled = row.exclude_status_toggle == 1 ? 'disabled' : '';
    let status = (value == "1") ? "checked" : "";
    return `<div class="form-check form-switch" text-center style="display: flex; justify-content: center;">
                <input class = "form-check-input switch1"id = "${row.id}" onclick = "chk(this);" data-url="${row.edit_status_url}" type="checkbox" role="switch" ${status} value="${value}" ${disabled}>
            </div>`;
}

function statusFormatter(value, row) {
    if (value == '1') {
        badgClass = 'success';
        badgeText = (window.trans['Active'] || 'Active');
    } else {
        badgClass = 'secondary';
        badgeText = (window.trans['Inactive'] || 'Inactive');
    }
    return '<span class="badge rounded-pill bg-' + badgClass + '">' + badgeText + '</span>';
}


function videoLinkFormatter(value) {
    if (value) {
        return `<a href="${value}" target="_blank">${(window.trans['Video Link'] || 'Video Link')}</a>`
    }
    return null;
}

function addedAsTagFormatter(value) {
    if (value === 'admin') {
        return '<span class="badge bg-primary">' + (window.trans['Admin'] || 'Admin') + '</span>';
    } else if (value === 'agent') {
        return '<span class="badge bg-info">' + (window.trans['Agent'] || 'Agent') + '</span>';
    } else if (value === 'general') {
        return '<span class="badge bg-warning">' + (window.trans['Everyone'] || 'Everyone') + '</span>';
    } else {
        return '<span class="badge bg-success">' + (window.trans['User'] || 'User') + '</span>';
    }
}

function expiryDateFormatter(value) {
    if (!value || value === '-') {
        return '-';
    }
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    var expiry = new Date(value);
    expiry.setHours(0, 0, 0, 0);
    var diffTime = expiry - today;
    var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    // Format date as dd-mm-yyyy
    var day = String(expiry.getDate()).padStart(2, '0');
    var month = String(expiry.getMonth() + 1).padStart(2, '0');
    var year = expiry.getFullYear();
    var formattedDate = day + '-' + month + '-' + year;

    if (diffDays < 0) {
        return formattedDate + '<br><span class="badge bg-light-danger text-danger">' + (window.trans['Expired'] || 'Expired') + '</span>';
    } else if (diffDays === 0) {
        return formattedDate + '<br><span class="badge bg-light-warning text-warning">' + (window.trans['Expires Today'] || 'Expires Today') + '</span>';
    } else {
        return formattedDate + '<br><span class="badge bg-light-success text-success">' + diffDays + ' ' + (window.trans['Days Left'] || 'Days Left') + '</span>';
    }
}

function projectTypeFormatter(value) {
    if (value == 'upcoming') {
        return `${(window.trans['Upcoming'] || 'Upcoming')}`
    }
    if (value == 'under_construction') {
        return `${(window.trans['Under Construction'] || 'Under Construction')}`
    }
    return null;
}

function fieldTypeFormatter(value) {
    return value.charAt(0).toUpperCase() + value.slice(1).toLowerCase();
}

function fieldValuesFormatter(value, row) {
    let html = '';
    if (row.form_fields_values.length > 0) {
        html += `<ul style="margin-bottom:0px; padding-left:0px;">`
        $.each(row.form_fields_values, function (index, valueData) {
            html += `<i class='fa fa-arrow-right' aria-hidden='true'></i> ${valueData.value}<br>`
        });
        html += `</ul>`
    } else {
        html = '<div>-</div>';
    }
    return html;

}

function userNameProfileFormatter(value) {
    let profileImage = value.profile ? value.profile : '/assets/images/placeholder/profile_placeholder.png';
    return `<div class="row align-items-center">
                <div class="col-md-2">
                    <a class="image-popup-no-margins" href="${profileImage}"><img class="rounded avatar-md shadow img-fluid" alt="" src="${profileImage}" onerror="this.onerror=null;this.src='/assets/images/faces/2.jpg';" width="55"></a>
                </div>
                <div class="ml-2 col-md-10">
                    <span>${value.name}</span>
                </div>
            </div>`;
}

function formTypeFormatter(value, row) {
    if (value === 'become_agent') {
        return '<span class="badge bg-primary">' + (window.trans['Become Agent'] || 'Become Agent') + '</span>';
    } else if (value === 'verify_agent') {
        return '<span class="badge bg-info">' + (window.trans['Verify Agent'] || 'Verify Agent') + '</span>';
    }
    return value;
}


function verifyCustomerStatusFormatter(value) {
    if (value == 'approved') {
        badgeClass = 'success';
        badgeText = (window.trans['Approved'] || 'Approved');
    } else if (value == 'rejected') {
        badgeClass = 'danger';
        badgeText = (window.trans['Rejected'] || 'Rejected');
    } else {
        badgeClass = 'warning';
        badgeText = (window.trans['Pending'] || 'Pending');
    }
    return '<span class="badge rounded-pill bg-' + badgeClass + '">' + badgeText + '</span>';
}

function requestStatusFormatter(value, row) {
    if (value == 'approved') {
        badgeClass = 'success';
        badgeText = (window.trans['Approved'] || 'Approved');
    } else if (value == 'rejected') {
        badgeClass = 'danger';
        badgeText = (window.trans['Rejected'] || 'Rejected');
    } else {
        badgeClass = 'warning';
        badgeText = (window.trans['Pending'] || 'Pending');
    }
    return '<span class="badge rounded-pill bg-' + badgeClass + '">' + badgeText + '</span>';
}
function customerLoginTypeFormatter(value) {
    if (value == 0) {
        badgeText = `<i class="bi bi-google" aria-hidden="true"></i>`
        badgeClass = "danger"
    } else if (value == 1) {
        badgeText = `<i class="fa fa-phone" aria-hidden="true"></i>`
        badgeClass = "success"
    } else if (value == 2) {
        badgeText = `<i class="bi bi-apple" aria-hidden="true"></i>`
        badgeClass = "secondary"
    } else if (value == 3) {
        badgeText = `<i class="fa fa-envelope" aria-hidden="true"></i>`
        badgeClass = "warning"
    }
    return '<span class="badge rounded-pill bg-' + badgeClass + '">' + badgeText + '</span>';
}

function projectEnableDisableSwitchFormatter(value, row) {
    if (row.is_admin_listing == 1 && value != null) {
        let status = (value == "1" || value == "active") ? "checked" : "";
        return `<div class="form-check form-switch" text-center style="display: flex; justify-content: center;">
            <input class = "form-check-input switch1"id = "${row.id}" onclick = "chk(this);" data-url="${row.edit_status_url}" type="checkbox" role="switch" ${status} value="${value}">
            </div>`;
    }
    return null;
}


function enableDisableFeaturedPropertiesFormatter(value, row) {
    let status = row.status == '1' ? 'checked' : '';
    return `<div class="form-check form-switch center" style="margin-top: 10%;padding-left: 5.2rem;">
                <input class="form-check-input switch1" id="${row.id}"  onclick="chk(this);" type="checkbox" role="switch" ${status} '>
            </div>`
}

function packageTypeFormatter(value) {
    let text = "";
    let classType = "";
    if (value == 'paid') {
        text = (window.trans["Paid"] || "Paid");
        classType = 'success'
    } else {
        text = (window.trans["Free"] || "Free");
        classType = 'warning'
    }
    return '<span class="badge rounded-pill bg-' + classType + '">' + text + '</span>';
}

function packageFeaturesFormatter(value) {
    let html = ``;
    html += `<ul style="margin-bottom:0px;">`
    $.each(value, function (index, data) {
        if (!data.feature) return true;
        var featureName = window.trans[data.feature.name] || data.feature.name;
        html += `<li>${featureName} - ${data.limit_type == 'unlimited' ? (window.trans['Unlimited'] || 'Unlimited') : (window.trans['Limited'] || 'Limited')} ${data.limit_type == 'limited' ? ` - ( ${data.limit} ${(window.trans['Limit'] || 'Limit')} )` : ''}</li>`;
    });
    html += `</ul>`
    return html;

}

function paymentStatusFormatter(value) {
    if (value == 'success') {
        text = (window.trans["Success"] || "Success");
        classType = 'success'
    } else if (value == 'failed') {
        text = (window.trans["Failed"] || "Failed");
        classType = 'danger'
    } else if (value == 'review') {
        text = window.trans["Review"] || 'Review';
        classType = 'info'
    } else if (value == 'rejected') {
        text = (window.trans["Rejected"] || "Rejected");
        classType = 'secondary'
    } else if (value == 're-uploaded') {
        text = (window.trans["Re-uploaded"] || "Re-uploaded");
        classType = 'primary'
    } else {
        text = (window.trans["Pending"] || "Pending");
        classType = 'warning'
    }
    return '<span class="badge rounded-pill bg-' + classType + '">' + text + '</span>';
}

function advertisementTypeFormatter(value) {
    if (value == 'property') {
        text = (window.trans["Property"] || "Property");
        classType = 'primary';
    } else if (value == 'project') {
        text = (window.trans["Project"] || "Project");
        classType = 'secondary';
    } else {
        return null;
    }
    return '<span class="badge rounded-pill bg-' + classType + '">' + text + '</span>';
}

function packagePriceFormatter(value, row) {
    if (value == null) {
        return '<span class="badge rounded-pill bg-warning">' + (window.trans["Free"] || "Free") + '</span>';
    }
    return row.price_symbol + ' ' + value;
}

function paymentAmountFormatter(value, row) {
    return row.price_symbol + ' ' + value;
}

function notificationCustomerFormatter(value, row) {
    let html = '';
    if (row.customer_data && row.customer_data.length > 0) {
        $.each(row.customer_data, function (index, customer) {
            if (customer.name) {
                html += `<li>${customer.name}</li>`;
            }
        });
        return `<ul> ${html} </ul>`;
    }
    return '<div class="text-center">-</div>';
}
// Appointment Status formatter
function appointmentStatusFormatter(value, row, index) {
    switch (row.status) {
        case 'pending':
            return `<span class="badge bg-warning">${(window.trans['Pending'] || 'Pending')}</span>`;
        case 'confirmed':
            return `<span class="badge bg-success">${(window.trans['Confirmed'] || 'Confirmed')}</span>`;
        case 'cancelled':
            return `<span class="badge bg-danger">${(window.trans['Cancelled'] || 'Cancelled')}</span>`;
        case 'completed':
            return `<span class="badge bg-info">${(window.trans['Completed'] || 'Completed')}</span>`;
        case 'rescheduled':
            return `<span class="badge bg-secondary">${(window.trans['Rescheduled'] || 'Rescheduled')}</span>`;
        case 'cancelled':
            return `<span class="badge bg-danger">${(window.trans['Cancelled'] || 'Cancelled')}</span>`;
        case 'auto_cancelled':
            return `<span class="badge bg-danger">${(window.trans['Auto Cancelled'] || 'Auto Cancelled')}</span>`;
        default:
            return null;
    }
}

function appointmentMeetingTypeFormatter(value) {
    if (value == 'in_person') {
        return `<span class="badge bg-primary">${(window.trans['In Person'] || 'In Person')}</span>`;
    } else if (value == 'virtual') {
        return `<span class="badge bg-secondary">${(window.trans['Virtual'] || 'Virtual')}</span>`;
    } else if (value == 'phone') {
        return `<span class="badge bg-success">${(window.trans['Phone'] || 'Phone')}</span>`;
    }
    return null;
}

function languageEnableDisableSwitchFormatter(value, row) {
    if (row.edit_status_url != null && value != null) {
        let status = (value == "1" || value == "active") ? "checked" : "";
        let disabled = row.is_disabled ? 'disabled' : '';
        return `<div class="form-check form-switch" text-center style="display: flex; justify-content: center;">
                <input class = "form-check-input switch1"id = "${row.id}" onclick = "chk(this,true);" data-url="${row.edit_status_url}" type="checkbox" role="switch" ${status} value="${value}" ${disabled}>
            </div>`;
    }
    return null;
}

function activeRoleFormatter(value, row) {
    if (value === 'agent') {
        return '<span class="badge rounded-pill bg-info">' + (window.trans['Agent'] || 'Agent') + '</span>';
    }
    return '<span class="badge rounded-pill bg-secondary">' + (window.trans['User'] || 'User') + '</span>';
}

function agentBadgeFormatter(value, row) {
    if (value === true || value === 1 || value === '1') {
        var badge = '<span class="badge rounded-pill bg-success">' + (window.trans['Yes'] || 'Yes') + '</span>';
        if (row.is_agent_verified === true || row.is_agent_verified === 1) {
            badge += ' <span class="badge rounded-pill bg-primary">' + (window.trans['Verified'] || 'Verified') + '</span>';
        }
        return badge;
    }
    return '<span class="badge rounded-pill bg-danger">' + (window.trans['No'] || 'No') + '</span>';
}

function addedAsFormatter(value, row) {
    if (row.added_by === 0 || row.is_admin_listing === 1) {
        return '<span class="badge rounded-pill bg-dark">' + (window.trans['Admin'] || 'Admin') + '</span>';
    }
    if (value === 'agent') {
        return '<span class="badge rounded-pill bg-info">' + (window.trans['Agent'] || 'Agent') + '</span>';
    }
    return '<span class="badge rounded-pill bg-secondary">' + (window.trans['User'] || 'User') + '</span>';
}
