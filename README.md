## Overview
This project is a full-stack calendar web application where users can register, log in, and create, edit, or delete their own events. It uses JavaScript and AJAX to communicate with the server and update the calendar in real time without reloading the page, providing a smooth single-page user experience.

## Key Features
- Month-by-month calendar view with unlimited navigation
- User registration and authentication
- Create, edit, and delete events
- Events are visible only to the authenticated user
- No page reloads (AJAX-based interactions)

## Extended Features

### Event Tagging & Filtering
 We added a tagging function that lets users categorize their events as Work, Event, Meeting, or Other.
 When a user adds a new event, if no tag is selected, the system automatically assigns the default tag “Work.”
 On the main calendar view, there are checkbox filters for each tag type displayed above the calendar. 
 When the page first loads, bascially every tags are selected by default, showing every event. 
 The user can then toggle the checkboxes to display only the categories they want to see.

### Group Events
In addition to personal events, users can create shared group events that appear
on multiple users’ calendars. When adding an event, the creator can select
**“Save as Group Event”** and enter the usernames of other participants separated
by commas. The system stores the event in the events table and links each
participant’s `user_id` through a `group_events` table, allowing the event to
appear on all participants’ calendars.  
Group events are marked with **[Group]** to distinguish them from personal
events. To avoid inconsistencies, only the event creator can edit group event
details such as the title, time, date, or color. However, each participant may
remove the group event from their own calendar without affecting others.

### Calendar Sharing
Users can share their entire calendar with other users through a **Share
Calendar** section on the page. By entering another user’s username and clicking
the Share button, the system links the two accounts in the database, allowing the
shared user to view all events from the owner’s calendar within their own
calendar view.

### Event Coloring
Events support customizable colors (**blue, green, yellow, red, purple**) for
better visual organization. A default color (**blue**) is applied if none is selected.
For group events, color changes made by the creator automatically propagate
to all participants’ calendars.

## Technical Highlights
- Client-side logic implemented in JavaScript
- Server-side APIs handle authentication and event management
- Session-based authorization to prevent unauthorized access
- All user and event data stored in a relational database
- Secure design: the server determines the active user from the session
  rather than trusting client-provided identifiers

## Tech Stack
- JavaScript (Frontend & AJAX)
- PHP (Server-side)
- MySQL (Database)
- HTML / CSS
