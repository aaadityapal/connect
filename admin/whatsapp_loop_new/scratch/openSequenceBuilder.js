function openSequenceBuilder(seqId = null) {
  const listView = document.getElementById('seq-list-view');
  const builderView = document.getElementById('seq-builder-view');
  if (!listView || !builderView) return;

  listView.classList.add('hidden');
  builderView.classList.remove('hidden');

  let seq;
  if (seqId) {
    seq = SEQUENCES.find(s => s.id === seqId);
    if (!seq) return;
    editingSequenceId = seqId;
  } else {
    // Create temporary new sequence
    const newId = Date.now();
    seq = {
      id: newId,
      title: "New Nurture Loop",
      desc: "Custom chronological drip sequence",
      stopOnReply: true,
      persistOnReply: false,
      steps: [
        { id: Date.now(), title: "Day 1 Welcome", templateKey: "welcome", delay_value: 1, delay_unit: "days" }
      ],
      enrolledClients: [],
      clientStates: {}
    };
    SEQUENCES.push(seq);
    editingSequenceId = newId;
  }

  // Populate title & settings
  const titleInput = document.getElementById('builder-seq-title');
  if (titleInput) titleInput.value = seq.title || seq.name || "Untitled Loop";

  renderBuilderSteps();
  populateClientEnrollDropdown();
  renderEnrolledClientsList();
  updateProjectedTimeline();

  // Log opening builder
  if (typeof logActivity === 'function') {
    logActivity({ type: 'Sequence Builder Open', page: 'sequences', data: { seqId: seq.id, title: seq.title } });
  }
}
