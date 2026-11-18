/* Fictional fixtures only. These objects are cloned into memory on each page load. */
"use strict";
window.KNS_SAMPLE_DATA = {
  today: "Tuesday, November 17, 2026",
  term: "1st semester · AY 2026–2027",
  profiles: {
    student: { id: "alex", name: "Alex Rivera", first: "Alex", initials: "AR", identifier: "2026-00042", email: "alex.rivera@example.test", detail: "BS Information Technology · 2nd year" },
    professor: { id: "mira", name: "Mira Santos", first: "Mira", initials: "MS", identifier: "FAC-026", email: "mira.santos@example.test", detail: "College of Computer Studies" }
  },
  classes: [
    { id: "web", code: "CC 104", title: "Web Systems & Technologies", section: "BSIT 2A", year: "2026–2027", semester: "1st semester", professorId: "mira", professor: "Mira Santos", initials: "WS", color: "mint", students: 28, capacity: 35, joinCode: "WEB-8K2", description: "Build thoughtful, accessible experiences for the web. A space to learn, experiment, and create together." },
    { id: "design", code: "HCI 201", title: "Human–Computer Interaction", section: "BSIT 2A", year: "2026–2027", semester: "1st semester", professorId: "mira", professor: "Mira Santos", initials: "HC", color: "lavender", students: 26, capacity: 35, joinCode: "HCI-2M6", description: "Explore how people think, interact, and find their way through digital experiences." },
    { id: "data", code: "CC 103", title: "Data Structures & Algorithms", section: "BSIT 2A", year: "2026–2027", semester: "1st semester", professorId: "noel", professor: "Noel Garcia", initials: "DS", color: "peach", students: 30, capacity: 35, joinCode: "DSA-9J3", description: "Turn complex problems into clear, efficient solutions, one idea at a time." },
    { id: "network", code: "NET 202", title: "Networking Fundamentals", section: "BSIT 2B", year: "2026–2027", semester: "1st semester", professorId: "mira", professor: "Mira Santos", initials: "NF", color: "sand", students: 17, capacity: 30, joinCode: "NET-4Q8", description: "Understand the connections that make the digital world work." }
  ],
  enrolled: ["web", "design", "data"],
  work: [
    { id: "responsive", classId: "web", title: "Build a responsive landing page", category: "Project", due: "2026-11-20", points: 50, status: "Published", mode: "both", instructions: "Create a landing page for a fictional local business. Include a clear navigation, a hero section, and a responsive three-column layout. Explain your layout decisions in a short paragraph and attach your screenshots or project file.", resources: [{ name: "Project-brief.pdf", size: "240 KB", kind: "PDF" }], responses: 18 },
    { id: "semantic", classId: "web", title: "Semantic HTML, in your own words", category: "Activity", due: "2026-11-18", points: 20, status: "Published", mode: "answer", instructions: "Choose three semantic HTML elements. Explain when you would use each, then describe how semantic markup helps a person who uses a screen reader.", resources: [], responses: 22 },
    { id: "wireframe", classId: "design", title: "From a user story to a wireframe", category: "Activity", due: "2026-11-16", points: 30, status: "Published", mode: "both", instructions: "Sketch a mobile library reservation screen. Include the user story, your wireframe, and a brief explanation of the primary action.", resources: [], responses: 24 },
    { id: "interview", classId: "design", title: "Plan a five-question user interview", category: "Project", due: "2026-11-24", points: 40, status: "Published", mode: "both", instructions: "Write five open-ended interview questions about a campus experience. Explain what you hope to learn from each question.", resources: [], responses: 6 },
    { id: "arrays", classId: "data", title: "Arrays & linked lists: a quick check", category: "Quiz", due: "2026-11-19", points: 15, status: "Published", mode: "answer", instructions: "Compare an array and a linked list. Give one example where each is a good choice and explain your reasoning.", resources: [], responses: 20 },
    { id: "network-map", classId: "network", title: "Map a small home network", category: "Activity", due: "2026-11-25", points: 25, status: "Published", mode: "both", instructions: "Draw a simple home network. Label the router, devices, and connections, and describe how data travels between two devices.", resources: [], responses: 3 },
    { id: "accessibility", classId: "web", title: "Accessibility review checklist", category: "Activity", due: "2026-11-27", points: 20, status: "Draft", mode: "both", instructions: "A sample draft for a future accessibility review.", resources: [], responses: 0 }
  ],
  updates: [
    { id: "u1", classId: "web", author: "Mira Santos", time: "Today · 9:15 AM", title: "A little guidance for Friday’s project", body: "Your layout doesn’t have to be complicated to be effective. Start with the mobile view, make the content easy to scan, and test your page at three different widths. I’ve added the project brief to Classwork.", pinned: true },
    { id: "u2", classId: "web", author: "Mira Santos", time: "Yesterday · 2:40 PM", title: "This week, we’re thinking responsively", body: "We’ll explore flexible grids, media queries, and the small details that make a website feel good on any screen. Bring one example of a website you enjoy using.", pinned: false },
    { id: "u3", classId: "design", author: "Mira Santos", time: "Today · 8:30 AM", title: "Good questions make better designs", body: "Before you start sketching, spend time with the user story. What does the person need to do? What might get in their way?", pinned: true },
    { id: "u4", classId: "data", author: "Noel Garcia", time: "Yesterday · 11:00 AM", title: "A fresh way to think about lists", body: "Our next discussion compares the trade-offs between contiguous and linked storage. Try drawing both approaches before writing code.", pinned: false },
    { id: "u5", classId: "network", author: "Mira Santos", time: "Monday · 10:00 AM", title: "Welcome to our networking classroom", body: "Introduce yourself in the group chat and tell us one thing you’re curious about. The first activity is ready in Classwork.", pinned: true }
  ],
  groupMessages: [
    { id: "g1", classId: "web", author: "Mira Santos", role: "professor", initials: "MS", text: "Welcome to our class space! Keep questions here helpful and kind. For a personal concern, use Private messages.", time: "9:15 AM", workId: "" },
    { id: "g2", classId: "web", author: "Kai Robles", role: "student", initials: "KR", text: "The mobile-first example from our last discussion finally clicked for me 🙌", time: "9:28 AM", workId: "" },
    { id: "g3", classId: "web", author: "Bea Cruz", role: "student", initials: "BC", text: "Same here! Is it okay to use CSS Grid for the project?", time: "9:31 AM", workId: "responsive" },
    { id: "g4", classId: "web", author: "Mira Santos", role: "professor", initials: "MS", text: "Absolutely. Grid or Flexbox are both welcome. Be ready to explain why you chose it.", time: "9:34 AM", workId: "responsive" }
  ],
  threads: [
    { id: "t-alex-web", classId: "web", studentId: "alex", student: "Alex Rivera", initials: "AR", messages: [
      { role: "student", text: "Good morning, Professor! Can I use a fictional café for the landing page?", time: "Yesterday · 10:12 AM", workId: "responsive" },
      { role: "professor", text: "Of course, Alex. A fictional café is a great choice. Focus on a clear call to action and how the layout adapts on mobile.", time: "Yesterday · 10:35 AM", workId: "responsive" }
    ] },
    { id: "t-kai-web", classId: "web", studentId: "kai", student: "Kai Robles", initials: "KR", messages: [
      { role: "student", text: "Should the project include screenshots for both mobile and desktop?", time: "Today · 8:40 AM", workId: "responsive" }
    ] },
    { id: "t-jules-web", classId: "web", studentId: "jules", student: "Jules Navarro", initials: "JN", messages: [
      { role: "student", text: "Could you clarify which screen widths we should test?", time: "Today · 8:55 AM", workId: "responsive" }
    ] },
    { id: "t-alex-design", classId: "design", studentId: "alex", student: "Alex Rivera", initials: "AR", messages: [
      { role: "professor", text: "Your wireframe has a clear main action, Alex. Take another look at the contrast on the secondary buttons.", time: "Yesterday · 3:20 PM", workId: "wireframe" }
    ] }
  ],
  submissions: [
    { id: "s-lia", workId: "responsive", studentId: "lia", student: "Lia Villanueva", initials: "LV", time: "Nov 17 · 8:42 AM", answer: "I started with a single-column mobile layout and used a grid above 768px. The primary action stays visible without distracting from the content.", files: [{ name: "cafe-landing-page.pdf", size: "1.2 MB", kind: "PDF" }, { name: "mobile-preview.png", size: "680 KB", kind: "Image" }], score: null, feedback: "" },
    { id: "s-kai", workId: "responsive", studentId: "kai", student: "Kai Robles", initials: "KR", time: "Nov 17 · 9:05 AM", answer: "My page uses Flexbox so the navigation and content can wrap naturally. I tested the layout on three viewport sizes.", files: [{ name: "responsive-project.pdf", size: "2.4 MB", kind: "PDF" }], score: null, feedback: "" },
    { id: "s-bea", workId: "responsive", studentId: "bea", student: "Bea Cruz", initials: "BC", time: "Nov 16 · 4:30 PM", answer: "I used a flexible grid and kept text widths comfortable on large screens. Buttons have enough space for touch interaction.", files: [{ name: "project-screenshots.pdf", size: "940 KB", kind: "PDF" }], score: 46, feedback: "A clear hierarchy and thoughtful mobile layout. Review the contrast of the small caption text." },
    { id: "s-alex-design", workId: "wireframe", studentId: "alex", student: "Alex Rivera", initials: "AR", time: "Nov 16 · 2:15 PM", answer: "As a student, I want to reserve an available book quickly. The screen puts search first and uses one clear reservation button.", files: [{ name: "library-wireframe.pdf", size: "820 KB", kind: "PDF" }], score: 27, feedback: "A strong user story and a focused primary action. Increase contrast on secondary buttons." }
  ],
  questionSignals: [
    { studentId: "kai", name: "Kai Robles", workId: "responsive", question: "Should we attach mobile and desktop screenshots?" },
    { studentId: "jules", name: "Jules Navarro", workId: "responsive", question: "Which screen widths should we test?" },
    { studentId: "bea", name: "Bea Cruz", workId: "responsive", question: "Can we use CSS Grid for the layout?" },
    { studentId: "lia", name: "Lia Villanueva", workId: "responsive", question: "Is there a required number of breakpoints?" },
    { studentId: "theo", name: "Theo Mendoza", workId: "responsive", question: "Do we need a separate tablet screenshot?" },
    { studentId: "nia", name: "Nia Flores", workId: "responsive", question: "What should we include in the layout explanation?" }
  ]
};
