
let currentDate = new Date();
let isEditing = false;

const tags = [
  { tag_id: 1, tag_name: 'Work', color: '#007bff' },
  { tag_id: 2, tag_name: 'Event', color: '#28a745' },
  { tag_id: 3, tag_name: 'Meeting', color: '#f39c12' },
  { tag_id: 4, tag_name: 'Other', color: '#6c757d' }
];

let activeTags = new Set(tags.map(t => t.tag_id));
const selectedOwners = new Set();


document.addEventListener('DOMContentLoaded', () => {
    renderCalendar(); /* Make the initial monthly calendar */
    renderTagFilters();  /* Show tag filter checkboxes */
    loadSharedEvents();  /*  Load shared events when page opens */
    loadSharedList();
    loadSharedWithOthers();    

    /*  Buttons for month navigation */
    document.getElementById('prevMonth').onclick = () => changeMonth(-1);
    document.getElementById('nextMonth').onclick = () => changeMonth(1);
    document.getElementById('closeModal').onclick = closeModal;
    document.getElementById('eventForm').onsubmit = submitEvent;
    document.getElementById('shareBtn').onclick = shareCalendar;

    document.querySelectorAll('.color-option').forEach(opt => {
        opt.addEventListener('click', function() {
            const selectedColor = this.getAttribute('data-color');
            document.getElementById('eventColor').value = selectedColor;
        
            document.querySelectorAll('.color-option').forEach(o => o.style.outline = '');
            this.style.outline = '3px solid black';
        });
    });

    const filterTag = document.getElementById('filterTag');
    if (filterTag) {
    filterTag.addEventListener('change', () => {
        const selected = filterTag.value;
        activeTags.clear();
        if (selected === 'all') {
            // Show all tags
            tags.forEach(t => activeTags.add(t.tag_id));
        } 
        else {
            // Show only the selected tag
            activeTags.add(parseInt(selected));
        }
        renderCalendar();
    });
    }

    const delBtn = document.getElementById('deleteEventBtn');
    if (delBtn) {
        delBtn.addEventListener('click', () => {
            const eventId = document.getElementById('eventId').value;
            if (!eventId) return;


            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('event_id', eventId);
            formData.append('csrf_token', document.getElementById('csrfToken').value);

            fetch('./php/event.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        closeModal();
                        renderCalendar(); 
                    }
                })
                .catch(err => console.error('Error while deleting event:', err));
        });
    }
});

/* Create and display the calendar grid for the current month */
function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];
    document.getElementById('monthYear').textContent = `${monthNames[month]} ${year}`;

    if (selectedOwners.size > 0) {
        const ownerId = [...selectedOwners][0];
        const activeLabel = document.querySelector(`#sharedList input[value="${ownerId}"]`)?.nextSibling?.textContent?.trim();
        if (activeLabel) {
            document.getElementById('monthYear').textContent = `${activeLabel}'s Calendar — ${monthNames[month]} ${year}`;
        }
    }

    const tbody = document.getElementById('calendar-body');
    tbody.innerHTML = '';
    document.querySelectorAll('.event').forEach(e => e.remove());
    let date = 1;

    for (let i = 0; i < 6; i++) {
        const row = document.createElement('tr');
        for (let j = 0; j < 7; j++) {
        const cell = document.createElement('td');
        if (i === 0 && j < firstDay || date > daysInMonth) {
            cell.textContent = '';
        } else {
            const dayForCell = date;
            cell.textContent = dayForCell;
            cell.onclick = () => {
                console.log("Clicked:", year, month + 1, dayForCell);
                openModal(year, month, dayForCell);
            };
            cell.id = `day-${year}-${month + 1}-${date}`;
            date++;

        }
        row.appendChild(cell);
        }
        tbody.appendChild(row);
    }
    // Only one calendar active at a time
    if (selectedOwners.size === 0) {
        // show my calendar
        loadEvents(); 
    } else {
        // show selected shared calendar
        loadSharedEvents(); 
    }
}

function changeMonth(step) {
    currentDate.setMonth(currentDate.getMonth() + step);
    renderCalendar();
}

// adding
function openModal(year, month, day) {
    const modal = document.getElementById('eventModal');
    const form = document.getElementById('eventForm');
    isEditing = false;

    document.getElementById('modalTitle').textContent = 'Add Event';
    form.reset();
    form.action.value = 'add';

    const makeGroup = document.getElementById('makeGroup');        
    const participants = document.getElementById('participants');   

    if (makeGroup) makeGroup.checked = false;                        
    if (participants) {                           
        participants.value = '';                 
        participants.style.display = 'none';      
    }                                                           

    // Ensure valid date even if day exceeds month length
    const correctedMonth = month + 1;
    const maxDay = new Date(year, correctedMonth, 0).getDate();
    const correctedDay = Math.min(day, maxDay);
    const selectedDate = `${year}-${String(correctedMonth).padStart(2, '0')}-${String(correctedDay).padStart(2, '0')}`;
    document.getElementById('eventDate').value = selectedDate;

    const now = new Date();
    const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    document.getElementById('eventTime').value = currentTime;
    
    //document.getElementById('eventDate').value =`${year}-${String(correctedMonth).padStart(2, '0')}-${String(correctedDay).padStart(2, '0')}`;

    modal.style.display = 'block';

    
    const delBtn = document.getElementById('deleteEventBtn');
    if (delBtn) delBtn.style.display = 'none';
}

// edit
function openEditModal(ev) {
    const modal = document.getElementById('eventModal');
    const form = document.getElementById('eventForm');
    isEditing = true;

    document.getElementById('modalTitle').textContent = 'Edit Event';
    form.action.value = 'edit';

    
    document.getElementById('eventId').value = ev.event_id;
    document.getElementById('eventDate').value = ev.event_date;
    document.getElementById('eventTime').value = ev.event_time;
    document.getElementById('eventTitle').value = ev.title;
    document.getElementById('eventDescription').value = ev.description;
    if (ev.color) {  /* Highlight the color used for this event */
    document.getElementById('eventColor').value = ev.color;

   
    document.querySelectorAll('.color-option').forEach(o => {
        o.style.outline = (o.getAttribute('data-color') === ev.color) ? '3px solid black' : '';
    });
}

    const makeGroup = document.getElementById('makeGroup');        
    const participants = document.getElementById('participants');   

    if (makeGroup) makeGroup.checked = false;       
    if (participants) {                             
        participants.value = '';                    
        participants.style.display = 'none';        
    } 

    modal.style.display = 'block';

    
    const delBtn = document.getElementById('deleteEventBtn');
    if (delBtn) delBtn.style.display = 'inline-block';
}


function closeModal() { 
    document.getElementById('eventModal').style.display = 'none'; 
}


function submitEvent(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('csrf_token', document.getElementById('csrfToken').value);
    formData.append('color', document.getElementById('eventColor').value);
    formData.append('tag_id', document.getElementById('eventTag').value);

    const makeGroup = document.getElementById('makeGroup');
    const participants = document.getElementById('participants');

    if (makeGroup && makeGroup.checked) {
        form.querySelector('input[name="action"]').value = 'group_add';
        formData.set('action', 'group_add');

        const usernames = participants.value.trim();
        if (!usernames) {
            alert("Please enter at least one username for group event.");
            return;
        }
        formData.append('participants', usernames);
    }

    fetch('./php/event.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeModal();
                renderCalendar();
            } else {
                alert(res.message || "Failed to save event.");
            }
        })
        .catch(err => console.error('Event submission failed:', err));
}



// Fetch all events for the current user
function loadEvents() {
    const formData = new FormData();
    formData.append('action', 'fetch');
    formData.append('csrf_token', document.getElementById('csrfToken').value);

    fetch('./php/event.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                renderEvents(res.events);
            } else {
                alert(res.message);
            }
        })
        .catch(err => console.error('Error loading events:', err));
}


// Add new event
function addEvent(title, date, time, description) {
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('title', title);
    formData.append('event_date', date);
    formData.append('event_time', time);
    formData.append('description', description);
    formData.append('csrf_token', document.getElementById('csrfToken').value);

    fetch('./php/event.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
        if (res.success) loadEvents();
        else alert(res.message);
        });
}

// Edit event
function editEvent(id, title, date, time, description) {
    const formData = new FormData();
    formData.append('action', 'edit');
    formData.append('event_id', id);
    formData.append('title', title);
    formData.append('event_date', date);
    formData.append('event_time', time);
    formData.append('description', description);
    formData.append('csrf_token', document.getElementById('csrfToken').value);

    fetch('./php/event.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
        if (res.success) loadEvents();
        else alert(res.message);
        });
}

// Delete event
function deleteEvent(id) {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('event_id', id);
    formData.append('csrf_token', document.getElementById('csrfToken').value);

    fetch('./php/event.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
        if (res.success) loadEvents();
        else alert(res.message);
        });
}

/* Display all events inside the correct day cell */
function renderEvents(events) {
    document.querySelectorAll('.event').forEach(e => e.remove());

    events.forEach(ev => {
        const [y, m, d] = ev.event_date.split('-').map(Number);
        if (y === currentDate.getFullYear() && m === currentDate.getMonth() + 1) {
        const cell = document.getElementById(`day-${y}-${m}-${d}`);
        if (!cell) return;

        // Use tag_id if present; show event if no tag (don’t filter it out)
        const tagId = ev.tag_id ?? (ev.tags?.[0]?.tag_id ?? null);
        if (tagId && !activeTags.has(tagId)) return;

        const tag = tagId ? tags.find(t => String(t.tag_id) === String(tagId)) : null;

        const div = document.createElement('div');
        div.className = 'event';
        if (ev.is_group === 1 || ev.group === true) {
            div.textContent = `[Group] ${ev.title}`;
            div.style.backgroundColor = ev.color || '#007bff';  
        } else {
            div.textContent = ev.title;
            div.style.backgroundColor = ev.color || tag?.color || '#007bff';
        }
        div.onclick = (event) => {
            event.stopPropagation();
            openEditModal(ev);
        };
        cell.appendChild(div);
        }
    });
}

function renderTagFilters() {
  const container = document.getElementById('tagFilters');
  container.innerHTML = '';
  tags.forEach(tag => {
    const label = document.createElement('label');
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.value = tag.tag_id;
    checkbox.checked = true;
    activeTags.add(tag.tag_id);

    checkbox.onchange = () => {
      if (checkbox.checked) activeTags.add(tag.tag_id);
      else activeTags.delete(tag.tag_id);
      renderCalendar();
    };

    label.appendChild(checkbox);
    label.append(` ${tag.tag_name}`);
    label.style.color = tag.color;
    label.style.marginRight = '10px';
    container.appendChild(label);
  });
}

// share your calendar with another user
function shareCalendar() {
    const username = document.getElementById('shareUsername').value.trim();
    const canEdit =  0;
    if (!username) return alert('Enter a username');
    // make data to send to server
    const formData = new FormData();
    formData.append('action', 'share');
    formData.append('username', username);
    formData.append('can_edit', canEdit);
    formData.append('csrf_token', document.getElementById('csrfToken').value);

    fetch('./php/event.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => alert(res.message));
    
}


// load events that other users shared with me
function loadSharedEvents() {
    if (selectedOwners.size === 0) return; // nothing selected

    const formData = new FormData();
    formData.append('action', 'shared_fetch');
    formData.append('csrf_token', document.getElementById('csrfToken').value);
    formData.append('owners', Array.from(selectedOwners).join(',')); // send chosen owners

    fetch('./php/event.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) renderSharedEvents(res.shared_events);
        })
        .catch(err => console.error('Error loading shared events:', err));
}

// show shared events on my calendar
function renderSharedEvents(events) {
    document.querySelectorAll('.event').forEach(e => e.remove()); // clear before render

    events.forEach(ev => {
        const [y, m, d] = ev.event_date.split('-').map(Number);
        if (y !== currentDate.getFullYear() || m !== currentDate.getMonth() + 1) return;

        const cell = document.getElementById(`day-${y}-${m}-${d}`);
        if (!cell) return;

        const tagId = ev.tag_id ?? (ev.tags?.[0]?.tag_id ?? null);
        if (tagId && !activeTags.has(tagId)) return;

        const tag = tagId ? tags.find(t => String(t.tag_id) === String(tagId)) : null;
        const div = document.createElement('div');
        div.className = 'event';

        if (ev.is_group === 1 || ev.group === true) {
            div.textContent = `[Group] ${ev.username}: ${ev.title}`;
            div.style.backgroundColor = ev.color || tag?.color || '#f39c12';
        } else {
            div.textContent = `${ev.username}: ${ev.title}`;
            div.style.backgroundColor = ev.color || tag?.color || '#28a745';
        }

        cell.appendChild(div);
    });
}

// Load the list of users who have shared their calendars with me
function loadSharedList() {
    const formData = new FormData();
    formData.append('action', 'shared_list');
    formData.append('csrf_token', document.getElementById('csrfToken').value);

    fetch('./php/event.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) renderSharedList(res.owners);
        })
        .catch(err => console.error('Error loading shared list:', err));
}

//  Render the list of users who have shared their calendars with me
function renderSharedList(owners) {
    const list = document.getElementById('sharedList');
    list.innerHTML = '';

    const myLabel = document.createElement('label');
    const myRadio = document.createElement('input');
    myRadio.type = 'radio';
    myRadio.name = 'calendarSelect';
    myRadio.value = 'mine';
    myRadio.checked = true;
    myRadio.onchange = () => {
        // no shared user selected
        selectedOwners.clear(); 
         // show my own events
        renderCalendar();      
    };
    myLabel.appendChild(myRadio);
    myLabel.append(' My Calendar');
    list.appendChild(myLabel);


    owners.forEach(o => {
        const label = document.createElement('label');
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'calendarSelect';
        radio.value = o.user_id;

        radio.onchange = () => {
            selectedOwners.clear();
            selectedOwners.add(o.user_id);
            // show that user's shared events
            renderCalendar();  
        };

        label.appendChild(radio);
        label.append(` ${o.username}`);
        list.appendChild(label);
    });
}

function loadSharedWithOthers() {
    const formData = new FormData();
    formData.append('action', 'shared_with_others');
    formData.append('csrf_token', document.getElementById('csrfToken').value);

    fetch('./php/event.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) renderSharedWithOthers(res.shared_users);
        })
        .catch(err => console.error('Error loading shared_with_others:', err));
}

// Render the list of users the calendar is shared with
function renderSharedWithOthers(users) {
    const section = document.getElementById('shareSection');
    let container = document.getElementById('sharedWithOthersList');
    if (!container) {
        container = document.createElement('div');
        container.id = 'sharedWithOthersList';
        section.appendChild(container);
    }
    container.innerHTML = '<h4>Currently Shared With:</h4>';

    if (users.length === 0) {
        container.innerHTML += '<p style="color:gray;">No one yet.</p>';
        return;
    }

    users.forEach(u => {
        const div = document.createElement('div');
        div.style.display = 'flex';
        div.style.alignItems = 'center';
        div.style.justifyContent = 'space-between';
        div.style.margin = '4px 0';
        div.innerHTML = `
            <span>${u.username} (${u.can_edit ? 'Edit' : 'View Only'})</span>
            <button class="unshareBtn" data-uid="${u.user_id}" style="padding:4px 10px; background:#dc3545; color:white; border:none; border-radius:4px; cursor:pointer;">Unshare</button>
        `;
        container.appendChild(div);
    });

    document.querySelectorAll('.unshareBtn').forEach(btn => {
        btn.onclick = () => unshareUser(btn.dataset.uid);
    });
}

// Unshare calendar with a specific user
function unshareUser(userId) {
    if (!confirm('Unshare your calendar with this user?')) return;

    const formData = new FormData();
    formData.append('action', 'unshare');
    formData.append('target_user_id', userId);
    formData.append('csrf_token', document.getElementById('csrfToken').value);

    fetch('./php/event.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            alert(res.message);
            if (res.success) loadSharedWithOthers();
        })
        .catch(err => console.error('Error unsharing calendar:', err));
}



