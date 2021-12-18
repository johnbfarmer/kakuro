export const Reducer = {
	universe: [1,2,3,4,5,6,7,8,9],
	pv: {3:{2:[[1,2]],3:[],4:[],5:[],6:[],7:[],8:[],9:[]},4:{2:[[1,3]],3:[],4:[],5:[],6:[],7:[],8:[],9:[]},5:{2:[[1,4],[2,3]],3:[],4:[],5:[],6:[],7:[],8:[],9:[]},6:{2:[[1,5],[2,4]],3:[[1,2,3]],4:[],5:[],6:[],7:[],8:[],9:[]},7:{2:[[1,6],[2,5],[3,4]],3:[[1,2,4]],4:[],5:[],6:[],7:[],8:[],9:[]},8:{2:[[1,7],[2,6],[3,5]],3:[[1,2,5],[1,3,4]],4:[],5:[],6:[],7:[],8:[],9:[]},9:{2:[[1,8],[2,7],[3,6],[4,5]],3:[[1,2,6],[1,3,5],[2,3,4]],4:[],5:[],6:[],7:[],8:[],9:[]},10:{2:[[1,9],[2,8],[3,7],[4,6]],3:[[1,2,7],[1,3,6],[1,4,5],[2,3,5]],4:[[1,2,3,4]],5:[],6:[],7:[],8:[],9:[]},11:{2:[[2,9],[3,8],[4,7],[5,6]],3:[[1,2,8],[1,3,7],[1,4,6],[2,3,6],[2,4,5]],4:[[1,2,3,5]],5:[],6:[],7:[],8:[],9:[]},12:{2:[[3,9],[4,8],[5,7]],3:[[1,2,9],[1,3,8],[1,4,7],[1,5,6],[2,3,7],[2,4,6],[3,4,5]],4:[[1,2,3,6],[1,2,4,5]],5:[],6:[],7:[],8:[],9:[]},13:{2:[[4,9],[5,8],[6,7]],3:[[1,3,9],[1,4,8],[1,5,7],[2,3,8],[2,4,7],[2,5,6],[3,4,6]],4:[[1,2,3,7],[1,2,4,6],[1,3,4,5]],5:[],6:[],7:[],8:[],9:[]},14:{2:[[5,9],[6,8]],3:[[1,4,9],[1,5,8],[1,6,7],[2,3,9],[2,4,8],[2,5,7],[3,4,7],[3,5,6]],4:[[1,2,3,8],[1,2,4,7],[1,2,5,6],[1,3,4,6],[2,3,4,5]],5:[],6:[],7:[],8:[],9:[]},15:{2:[[6,9],[7,8]],3:[[1,5,9],[1,6,8],[2,4,9],[2,5,8],[2,6,7],[3,4,8],[3,5,7],[4,5,6]],4:[[1,2,3,9],[1,2,4,8],[1,2,5,7],[1,3,4,7],[1,3,5,6],[2,3,4,6]],5:[[1,2,3,4,5]],6:[],7:[],8:[],9:[]},16:{2:[[7,9]],3:[[1,6,9],[1,7,8],[2,5,9],[2,6,8],[3,4,9],[3,5,8],[3,6,7],[4,5,7]],4:[[1,2,4,9],[1,2,5,8],[1,2,6,7],[1,3,4,8],[1,3,5,7],[1,4,5,6],[2,3,4,7],[2,3,5,6]],5:[[1,2,3,4,6]],6:[],7:[],8:[],9:[]},17:{2:[[8,9]],3:[[1,7,9],[2,6,9],[2,7,8],[3,5,9],[3,6,8],[4,5,8],[4,6,7]],4:[[1,2,5,9],[1,2,6,8],[1,3,4,9],[1,3,5,8],[1,3,6,7],[1,4,5,7],[2,3,4,8],[2,3,5,7],[2,4,5,6]],5:[[1,2,3,4,7],[1,2,3,5,6]],6:[],7:[],8:[],9:[]},18:{2:[],3:[[1,8,9],[2,7,9],[3,6,9],[3,7,8],[4,5,9],[4,6,8],[5,6,7]],4:[[1,2,6,9],[1,2,7,8],[1,3,5,9],[1,3,6,8],[1,4,5,8],[1,4,6,7],[2,3,4,9],[2,3,5,8],[2,3,6,7],[2,4,5,7],[3,4,5,6]],5:[[1,2,3,4,8],[1,2,3,5,7],[1,2,4,5,6]],6:[],7:[],8:[],9:[]},19:{2:[],3:[[2,8,9],[3,7,9],[4,6,9],[4,7,8],[5,6,8]],4:[[1,2,7,9],[1,3,6,9],[1,3,7,8],[1,4,5,9],[1,4,6,8],[1,5,6,7],[2,3,5,9],[2,3,6,8],[2,4,5,8],[2,4,6,7],[3,4,5,7]],5:[[1,2,3,4,9],[1,2,3,5,8],[1,2,3,6,7],[1,2,4,5,7],[1,3,4,5,6]],6:[],7:[],8:[],9:[]},20:{2:[],3:[[3,8,9],[4,7,9],[5,6,9],[5,7,8]],4:[[1,2,8,9],[1,3,7,9],[1,4,6,9],[1,4,7,8],[1,5,6,8],[2,3,6,9],[2,3,7,8],[2,4,5,9],[2,4,6,8],[2,5,6,7],[3,4,5,8],[3,4,6,7]],5:[[1,2,3,5,9],[1,2,3,6,8],[1,2,4,5,8],[1,2,4,6,7],[1,3,4,5,7],[2,3,4,5,6]],6:[],7:[],8:[],9:[]},21:{2:[],3:[[4,8,9],[5,7,9],[6,7,8]],4:[[1,3,8,9],[1,4,7,9],[1,5,6,9],[1,5,7,8],[2,3,7,9],[2,4,6,9],[2,4,7,8],[2,5,6,8],[3,4,5,9],[3,4,6,8],[3,5,6,7]],5:[[1,2,3,6,9],[1,2,3,7,8],[1,2,4,5,9],[1,2,4,6,8],[1,2,5,6,7],[1,3,4,5,8],[1,3,4,6,7],[2,3,4,5,7]],6:[[1,2,3,4,5,6]],7:[],8:[],9:[]},22:{2:[],3:[[5,8,9],[6,7,9]],4:[[1,4,8,9],[1,5,7,9],[1,6,7,8],[2,3,8,9],[2,4,7,9],[2,5,6,9],[2,5,7,8],[3,4,6,9],[3,4,7,8],[3,5,6,8],[4,5,6,7]],5:[[1,2,3,7,9],[1,2,4,6,9],[1,2,4,7,8],[1,2,5,6,8],[1,3,4,5,9],[1,3,4,6,8],[1,3,5,6,7],[2,3,4,5,8],[2,3,4,6,7]],6:[[1,2,3,4,5,7]],7:[],8:[],9:[]},23:{2:[],3:[[6,8,9]],4:[[1,5,8,9],[1,6,7,9],[2,4,8,9],[2,5,7,9],[2,6,7,8],[3,4,7,9],[3,5,6,9],[3,5,7,8],[4,5,6,8]],5:[[1,2,3,8,9],[1,2,4,7,9],[1,2,5,6,9],[1,2,5,7,8],[1,3,4,6,9],[1,3,4,7,8],[1,3,5,6,8],[1,4,5,6,7],[2,3,4,5,9],[2,3,4,6,8],[2,3,5,6,7]],6:[[1,2,3,4,5,8],[1,2,3,4,6,7]],7:[],8:[],9:[]},24:{2:[],3:[[7,8,9]],4:[[1,6,8,9],[2,5,8,9],[2,6,7,9],[3,4,8,9],[3,5,7,9],[3,6,7,8],[4,5,6,9],[4,5,7,8]],5:[[1,2,4,8,9],[1,2,5,7,9],[1,2,6,7,8],[1,3,4,7,9],[1,3,5,6,9],[1,3,5,7,8],[1,4,5,6,8],[2,3,4,6,9],[2,3,4,7,8],[2,3,5,6,8],[2,4,5,6,7]],6:[[1,2,3,4,5,9],[1,2,3,4,6,8],[1,2,3,5,6,7]],7:[],8:[],9:[]},25:{2:[],3:[],4:[[1,7,8,9],[2,6,8,9],[3,5,8,9],[3,6,7,9],[4,5,7,9],[4,6,7,8]],5:[[1,2,5,8,9],[1,2,6,7,9],[1,3,4,8,9],[1,3,5,7,9],[1,3,6,7,8],[1,4,5,6,9],[1,4,5,7,8],[2,3,4,7,9],[2,3,5,6,9],[2,3,5,7,8],[2,4,5,6,8],[3,4,5,6,7]],6:[[1,2,3,4,6,9],[1,2,3,4,7,8],[1,2,3,5,6,8],[1,2,4,5,6,7]],7:[],8:[],9:[]},26:{2:[],3:[],4:[[2,7,8,9],[3,6,8,9],[4,5,8,9],[4,6,7,9],[5,6,7,8]],5:[[1,2,6,8,9],[1,3,5,8,9],[1,3,6,7,9],[1,4,5,7,9],[1,4,6,7,8],[2,3,4,8,9],[2,3,5,7,9],[2,3,6,7,8],[2,4,5,6,9],[2,4,5,7,8],[3,4,5,6,8]],6:[[1,2,3,4,7,9],[1,2,3,5,6,9],[1,2,3,5,7,8],[1,2,4,5,6,8],[1,3,4,5,6,7]],7:[],8:[],9:[]},27:{2:[],3:[],4:[[3,7,8,9],[4,6,8,9],[5,6,7,9]],5:[[1,2,7,8,9],[1,3,6,8,9],[1,4,5,8,9],[1,4,6,7,9],[1,5,6,7,8],[2,3,5,8,9],[2,3,6,7,9],[2,4,5,7,9],[2,4,6,7,8],[3,4,5,6,9],[3,4,5,7,8]],6:[[1,2,3,4,8,9],[1,2,3,5,7,9],[1,2,3,6,7,8],[1,2,4,5,6,9],[1,2,4,5,7,8],[1,3,4,5,6,8],[2,3,4,5,6,7]],7:[],8:[],9:[]},28:{2:[],3:[],4:[[4,7,8,9],[5,6,8,9]],5:[[1,3,7,8,9],[1,4,6,8,9],[1,5,6,7,9],[2,3,6,8,9],[2,4,5,8,9],[2,4,6,7,9],[2,5,6,7,8],[3,4,5,7,9],[3,4,6,7,8]],6:[[1,2,3,5,8,9],[1,2,3,6,7,9],[1,2,4,5,7,9],[1,2,4,6,7,8],[1,3,4,5,6,9],[1,3,4,5,7,8],[2,3,4,5,6,8]],7:[[1,2,3,4,5,6,7]],8:[],9:[]},29:{2:[],3:[],4:[[5,7,8,9]],5:[[1,4,7,8,9],[1,5,6,8,9],[2,3,7,8,9],[2,4,6,8,9],[2,5,6,7,9],[3,4,5,8,9],[3,4,6,7,9],[3,5,6,7,8]],6:[[1,2,3,6,8,9],[1,2,4,5,8,9],[1,2,4,6,7,9],[1,2,5,6,7,8],[1,3,4,5,7,9],[1,3,4,6,7,8],[2,3,4,5,6,9],[2,3,4,5,7,8]],7:[[1,2,3,4,5,6,8]],8:[],9:[]},30:{2:[],3:[],4:[[6,7,8,9]],5:[[1,5,7,8,9],[2,4,7,8,9],[2,5,6,8,9],[3,4,6,8,9],[3,5,6,7,9],[4,5,6,7,8]],6:[[1,2,3,7,8,9],[1,2,4,6,8,9],[1,2,5,6,7,9],[1,3,4,5,8,9],[1,3,4,6,7,9],[1,3,5,6,7,8],[2,3,4,5,7,9],[2,3,4,6,7,8]],7:[[1,2,3,4,5,6,9],[1,2,3,4,5,7,8]],8:[],9:[]},31:{2:[],3:[],4:[],5:[[1,6,7,8,9],[2,5,7,8,9],[3,4,7,8,9],[3,5,6,8,9],[4,5,6,7,9]],6:[[1,2,4,7,8,9],[1,2,5,6,8,9],[1,3,4,6,8,9],[1,3,5,6,7,9],[1,4,5,6,7,8],[2,3,4,5,8,9],[2,3,4,6,7,9],[2,3,5,6,7,8]],7:[[1,2,3,4,5,7,9],[1,2,3,4,6,7,8]],8:[],9:[]},32:{2:[],3:[],4:[],5:[[2,6,7,8,9],[3,5,7,8,9],[4,5,6,8,9]],6:[[1,2,5,7,8,9],[1,3,4,7,8,9],[1,3,5,6,8,9],[1,4,5,6,7,9],[2,3,4,6,8,9],[2,3,5,6,7,9],[2,4,5,6,7,8]],7:[[1,2,3,4,5,8,9],[1,2,3,4,6,7,9],[1,2,3,5,6,7,8]],8:[],9:[]},33:{2:[],3:[],4:[],5:[[3,6,7,8,9],[4,5,7,8,9]],6:[[1,2,6,7,8,9],[1,3,5,7,8,9],[1,4,5,6,8,9],[2,3,4,7,8,9],[2,3,5,6,8,9],[2,4,5,6,7,9],[3,4,5,6,7,8]],7:[[1,2,3,4,6,8,9],[1,2,3,5,6,7,9],[1,2,4,5,6,7,8]],8:[],9:[]},34:{2:[],3:[],4:[],5:[[4,6,7,8,9]],6:[[1,3,6,7,8,9],[1,4,5,7,8,9],[2,3,5,7,8,9],[2,4,5,6,8,9],[3,4,5,6,7,9]],7:[[1,2,3,4,7,8,9],[1,2,3,5,6,8,9],[1,2,4,5,6,7,9],[1,3,4,5,6,7,8]],8:[],9:[]},35:{2:[],3:[],4:[],5:[[5,6,7,8,9]],6:[[1,4,6,7,8,9],[2,3,6,7,8,9],[2,4,5,7,8,9],[3,4,5,6,8,9]],7:[[1,2,3,5,7,8,9],[1,2,4,5,6,8,9],[1,3,4,5,6,7,9],[2,3,4,5,6,7,8]],8:[],9:[]},36:{2:[],3:[],4:[],5:[],6:[[1,5,6,7,8,9],[2,4,6,7,8,9],[3,4,5,7,8,9]],7:[[1,2,3,6,7,8,9],[1,2,4,5,7,8,9],[1,3,4,5,6,8,9],[2,3,4,5,6,7,9]],8:[[1,2,3,4,5,6,7,8]],9:[]},37:{2:[],3:[],4:[],5:[],6:[[2,5,6,7,8,9],[3,4,6,7,8,9]],7:[[1,2,4,6,7,8,9],[1,3,4,5,7,8,9],[2,3,4,5,6,8,9]],8:[[1,2,3,4,5,6,7,9]],9:[]},38:{2:[],3:[],4:[],5:[],6:[[3,5,6,7,8,9]],7:[[1,2,5,6,7,8,9],[1,3,4,6,7,8,9],[2,3,4,5,7,8,9]],8:[[1,2,3,4,5,6,8,9]],9:[]},39:{2:[],3:[],4:[],5:[],6:[[4,5,6,7,8,9]],7:[[1,3,5,6,7,8,9],[2,3,4,6,7,8,9]],8:[[1,2,3,4,5,7,8,9]],9:[]},40:{2:[],3:[],4:[],5:[],6:[],7:[[1,4,5,6,7,8,9],[2,3,5,6,7,8,9]],8:[[1,2,3,4,6,7,8,9]],9:[]},41:{2:[],3:[],4:[],5:[],6:[],7:[[2,4,5,6,7,8,9]],8:[[1,2,3,5,6,7,8,9]],9:[]},42:{2:[],3:[],4:[],5:[],6:[],7:[[3,4,5,6,7,8,9]],8:[[1,2,4,5,6,7,8,9]],9:[]},43:{2:[],3:[],4:[],5:[],6:[],7:[],8:[[1,3,4,5,6,7,8,9]],9:[]},44:{2:[],3:[],4:[],5:[],6:[],7:[],8:[[2,3,4,5,6,7,8,9]],9:[]},45:{2:[],3:[],4:[],5:[],6:[],7:[],8:[],9:[[1,2,3,4,5,6,7,8,9]]}},
	messages: [''],
	height: 0,
	width: 0,
	failLevel: 100,

	reduce(level, c, i, cs, s, h, w) {
		if (!h) {
			h = this.height;
		}
		if (!w) {
			w = this.width;
		}

		this.height = h;
		this.width = w;

		let vals = { cells: c, strips: s};
		if (level === 0) {
			vals = this.getAllStrips(c, s, h, w);
			return { cells: vals.cells, strips: vals.strips, changedStrips: [], level: 10, msg: this.messages}
		}

		if (level === 10) {
			vals = this.reductionStepOne(10, c, cs, i, s);
			let changedStrips = vals.changedStrips.length ? vals.changedStrips : cs;
			level = vals.changedStrips.length ? 10 : 20;
			if (level === 20) {
				for (let sidx in vals.strips) {
					vals.strips[sidx].changed = true;
					changedStrips.push(sidx);
				}
			}
			return { cells: vals.cells, strips: vals.strips, changedStrips, level, msg: this.messages}
		}

		if (level > 10) {
			this.messages = [''];
			vals = this.reduceAllByIsComplementPossible(level, cs, i, vals.cells, vals.strips);
			level = vals.changedStrips.length ? level : level + 10;
			return { cells: vals.cells, strips: vals.strips, level, changedStrips: vals.changedStrips, msg: this.messages };
		}

	},

	getAllStrips(c,s,h,w) {
		let vals;
		c.forEach((cell, idx) => {
			vals = this.getMyStrips(c, idx, s, h, w);
			c = vals.cells;
			s = vals.strips;
		});

		return {cells: c, strips: s};
	},

	reductionStepOne(level, c, cs, i, s) {
		console.log('reductionStepOne',level, cs, i);
		// if i < 0, and no cs, get web; else use cs
		// if i >= 0, get i's strips
		if (i >= 0) {
			cs = JSON.parse(JSON.stringify(c[i].strips));
		} else {
			if (!cs.length) {
				cs = this.buildStripWeb(i, c, s);
			}
		}
		let vals = {cells: c, strips: s, changedStrips: [], level};
		let stripIdx;
		while (stripIdx = cs.shift()) {	
			let strip = s[stripIdx];
			if (!strip.changed) {
				continue;
			}
			strip.cells.forEach(cellIdx => {
				vals = this.fillPossibleValues(vals.cells, vals.strips, cellIdx, 0, level);
				vals.changedStrips.forEach(vcs => {
					if (cs.indexOf(vcs) < 0) {
						cs.push(vcs);
					}
				});
				vals = this.fillPossibleValues(vals.cells, vals.strips, cellIdx, 1, level);
				vals.changedStrips.forEach(vcs => {
					if (cs.indexOf(vcs) < 0) {
						cs.push(vcs);
					}
				});
			});
		};
		let cells = vals.cells;
		let strips = vals.strips;

		return { cells, strips, changedStrips: cs, level };
	},

	reduceByElimination(cells, cs, cidx, sidx, strips, level) {
		let cell = cells[cidx];
		let valToRemove = cell.choices[0];
		cell.strips.forEach(stripIdx => {
			let strip = strips[stripIdx];
			if (strip.changed || stripIdx !== sidx) { // sidx was just removed from cs before this and marked unchanged
				strip.cells.forEach(cellIdx => {
					let c = cells[cellIdx];
					if (c.choices.length > 1 && c.choices.indexOf(valToRemove) >= 0) {
						let ch = [];
						c.choices.forEach(choice => {
							if (choice !== valToRemove) {
								ch.push(choice);
							}
						});
						c.choices = ch;
						strip.changed = true;
						if (!ch.length) {
							level = this.failLevel;
						}
					}
				});
				if (strip.changed) {
					if (cs.indexOf(stripIdx) < 0) {
						cs.push(stripIdx);
					}
				}
			}
		});

		return {cells, strips, cs, level}
	},

	reduceAllByIsComplementPossible(level, cs, i, cells, strips) {
		if (!cs.length) {
			cs = this.buildStripWeb(i, cells, strips);
		}

		let hasChanges = false;
		let sidx;

		while (sidx = cs.shift()) {
			let s = strips[sidx];
console.log('line 141', s.unknown, s.changed);			
			if (!s.changed) {
				continue;
			}
			s.changed = false;
			strips[sidx].cells.some(cidx => {
				let oldChoices = cells[cidx].choices;
				if (oldChoices.length === 1) {
					let reducedByElimination = this.reduceByElimination(cells, cs, cidx, sidx, strips, level);
					level = reducedByElimination.level;
					cells = reducedByElimination.cells;
					cidx = reducedByElimination.cidx;
					strips = reducedByElimination.strips;
 					return false;
				}
				let d = this.reductionByIsComplementPossible(level, cells, cidx, strips);
				let newChoices = cells[cidx].choices;
				if (newChoices.length < 1) {
					console.log('no choices');
					cs = [];
					level = this.failLevel; // or whatever indicates no solution
					return true;
				}
				if (oldChoices.length !== newChoices.length) {
					hasChanges = true;
					cells[cidx].strips.forEach(changedStripIdx => {
						cs.push(changedStripIdx);
						strips[changedStripIdx].changed = true;
					});
					// console.log(this.coords(cells[cidx]) + ' choices changed from ' + JSON.stringify(oldChoices) + ' to ' + JSON.stringify(newChoices));
					this.messages.push(this.coords(cells[cidx]) + ' choices changed from ' + JSON.stringify(oldChoices) + ' to ' + JSON.stringify(newChoices));
					return true;
				}
			});

			if (hasChanges) {
				break;
			}
		};
		cs.sort((x,y)=>{return strips[x].unknown - strips[y].unknown});
		level = cs.length ? level : 100;

		return { cells, strips, changedStrips: cs, level };
	},

	reductionByIsComplementPossible(level, cells, thisCellIdx, strips) {
		let cell = cells[thisCellIdx];
		let valueIsPossible = true;
		let newChoices = [];
		let oldChoices = cell.choices;
		oldChoices.forEach(choice => {
			valueIsPossible = cell.strips.every(stripIdx => {
				let strip = strips[stripIdx];
				let vals = [[choice]];
				let sum = strip.sum;
				strip.cells.forEach(cellIdx => {
					if (cellIdx !== thisCellIdx) {
						vals.push(JSON.parse(JSON.stringify(cells[cellIdx].choices)));
					}
				});
				vals = this.sortByLength(vals);
				if (!this.isPossible(strip.sum, JSON.parse(JSON.stringify(vals)), JSON.parse(JSON.stringify(strip.possibleSets)))) {
this.messages.push(this.coords(cell) + ' choice ' + choice + ' NOT ok. Cannot make sum ' + strip.sum + ' with vals ' + JSON.stringify(vals) + ' and possible sets ' + JSON.stringify(strip.possibleSets));					
					return false;
				}
// this.messages.push(this.coords(cell) + ' choice ' + choice + ' ok');					
				return true;
			});
			if (valueIsPossible) {
				newChoices.push(choice);
			}
		});
		cells[thisCellIdx].choices = newChoices;
		if (oldChoices.length != newChoices.length) {
			this.messages.push(this.coords(cell) + ' choices now ' + JSON.stringify(newChoices));
			if (newChoices.count === 1) {
				cells[thisCellIdx].known = true;
				cells[thisCellIdx].strips.forEach(sidx => {
					strips[sidx].unknown--;
				});
			}
		}

		return { cells, strips }
	},

	isPossible(sum, vals, combinations, taken = [], nestingLevel = 0) {
		// example sum=3, vals=[[2,3],[1]], com=[[1,2]] => sum=2, vals=[[2,3]], com=[[2]] => true
		// example sum=3, vals=[[2,3],[1,2]], com=[[1,2]] => sum=1, vals=[[1,2]], com=[[1]] => true
		// let indent = ''; if (nestingLevel) {for (let i=1;i<=nestingLevel;i++){indent = indent + '  '}}
// console.log(indent + 'isPossible', sum, JSON.stringify(vals), JSON.stringify(combinations));
		if (sum < 0) {
			return false;
		}
		if (++nestingLevel > 8) {
			// console.log(indent + 'nesting level too deep');
			return false;
		}
		if (vals.length === 1) {
// console.log(indent + 'len is 1', vals[0].indexOf(sum) >= 0, combinations.length === 1, combinations[0].length === 1, combinations[0][0] === sum);
			return vals[0].indexOf(sum) >= 0 && combinations.length === 1 && combinations[0].length === 1 && combinations[0][0] === sum;
		}
		// vals = this.sortByLength(vals); // avoid AMAP -- apply before sending here
		let intersectionCombos = this.intersection(JSON.parse(JSON.stringify(combinations))); // anything in the intersection must be in the union 
		let unionChoices = this.union(JSON.parse(JSON.stringify(vals)));
		let unionCombos = this.union(JSON.parse(JSON.stringify(combinations)));
// console.log(indent + "vals, intersectionCombos, unionChoices", JSON.stringify(vals), JSON.stringify(intersectionCombos), JSON.stringify(unionChoices));
		if (this.diff(intersectionCombos, unionChoices).length > 0) {
			return false;
		}
		return vals.some((v,i) => {
// console.log(indent + 'val', JSON.stringify(v));
			let otherVals = [];
			vals.forEach((vv, ii) => {
				if (ii!==i) {
					if (vv && vv.length) {
						otherVals.push(vv);
					}
				}
			}); // [[2,3]] [[1,2]]
			return v.some(choice => {
				if (unionCombos.indexOf(choice) < 0) {
// console.log(indent + 'choice ' + choice + ' not in ' + JSON.stringify(unionCombos));
					return false;
				}
				taken.push(choice);
				let combos = [];
				let ov = [];
				let allGood = true;
				let possibleCellVals = [];

				otherVals.forEach((cellVals, ii) => {
					if (allGood && ii !== i) {
						possibleCellVals = [];
						cellVals.forEach(val => {
							if (taken.indexOf(val) < 0) {
								possibleCellVals.push(val);
							}
						});
						if (possibleCellVals.length < 1) {
							allGood = false;
						} else {
							ov.push(possibleCellVals);
						}
					}
				});

				if (!allGood) {
// console.log(indent + 'some cell would be empty', sum, choice);
					taken.pop();
					return false;
				}

				if (!ov.length || (ov.length === 1 && !ov[0].length)) { // should not happen anymore
// console.log(indent + 'ov is empty', sum, choice);
					taken.pop();
					return false;
				}
				combinations.forEach((c) => {
					if (c.indexOf(choice) >= 0) {
						let elts = [];
						c.forEach(e => { if (e !== choice) elts.push(e);});
						combos.push(elts);
					}
				});
// console.log(indent + 'recurse on choice, ov, combos, taken', choice, JSON.stringify(ov), JSON.stringify(combos), JSON.stringify(taken));
// console.log(indent + 'vals, combinations, taken, nestingLevel', JSON.stringify(vals), JSON.stringify(combinations), JSON.stringify(taken), nestingLevel);
				let ret = this.isPossible(sum - choice, ov, combos, taken, nestingLevel);
				if (!ret) {
					taken.pop();
					return false;
				}

				return true;
			});
		});

		return false;
	},

	sortByLength(arr) {
		return arr.sort((a,b) => {return a.length - b.length});
	},

	fillPossibleValues(cells, strips, idx, orientation, level) {
// console.log('line 326', cells[idx], orientation, cells[idx].strips[orientation]);
		let strip = strips[cells[idx].strips[orientation]];
		let changedStrips = [];
		if (!strip.changed) {
			return { cells, strips, changedStrips };
		}
		let i = -1;
		// if (level < 20) {
		// 	i = idx;
		// }
		let vals = this.getChoices(cells, strip, i, strips);
		cells = vals.cells;
		strips = vals.strips;
		changedStrips = vals.changedStrips;
		strips[cells[idx].strips[orientation]] = vals.strip;
console.log(changedStrips)
		return { cells, strips, changedStrips };
	},

	possibleValues(sum, howMany, used = [], level = 0) {
this.messages.push(sum + ' over ' + howMany + ' without ' + JSON.stringify(used) + ' possible?');
		used.forEach(u => {
			sum = sum - u;
			howMany = howMany - 1;
		});
		return this.possibleValuesSubroutine(sum, howMany, used, level);
	},

	possibleValuesSubroutine(sum, howMany, used = [], level = 0) {
		let workingSet = [];
		this.universe.forEach(v => {
			if (used.indexOf(v) < 0) {
				workingSet.push(v);
			}
		});
		if (sum <= 0) {
			return [];
		}
		if (howMany === 0) {
			return [];
		}
		if (howMany === 1) {
			if (workingSet.indexOf(sum) >= 0) {
				return [sum];
			}
			return [];
		}
		let returnSet = JSON.parse(JSON.stringify(this.pv[sum][howMany]));
		if (!used.length) {
			return returnSet;
		}

		let idxsToRemove = [];
		returnSet.forEach((t, idx) => {
			used.forEach(u => {
				if (t.indexOf(u) >= 0 && idxsToRemove.indexOf(idx) < 0) {
					idxsToRemove.push(idx);
				}
			});
		});

		idxsToRemove.reverse().forEach((idx) => {
			returnSet.splice(idx, 1);
		});

		return returnSet;
	},

	getChoices(cells, strip, idx, strips) {
		let changedStrips = [];
		let choices, newChoices;
		strips[strip.idx].possibleSets = this.possibleValues(strip.sum, strip.cells.length);
		strips[strip.idx].changed = false;
		strip.cells.forEach(i => {
			if (idx < 0 || idx === i) {
				choices = JSON.parse(JSON.stringify(cells[i].choices));
				let pv = this.union(strip.possibleSets);
				newChoices = choices.length > 0 ? this.intersect(cells[i].choices, pv) : pv;
				cells[i].choices = newChoices;
				if (choices.length !== newChoices.length) {
					changedStrips.push(cells[i].strips[0]);
					changedStrips.push(cells[i].strips[1]);
this.messages.push('changedStrips for ' + i +': ' +  JSON.stringify(changedStrips));
// console.log('changedStrips for ' + i +': ' +  JSON.stringify(changedStrips));
					this.messages.push(this.coords(cells[i]) + ' set to ' + JSON.stringify(cells[i].choices));
				}
				if (newChoices.length === 1) {
					cells[i].known = true;
					cells[i].strips.forEach(sidx => {
						strips[sidx].unknown--;
					});
				}
			}
		});

		return { cells, strip, changedStrips, strips };
	},

	getMyStrips(cells, idx, strips, h, w) {
		if (!cells[idx].is_data) {
			return { cells, strips };
		}
		if (cells[idx].strips[0].length && cells[idx].strips[1].length) {
			return { cells, strips };
		}

        let strip = {cells:[], changed: true};
        let firstCellRow = 0;
        let firstCellCol = 0;
        let sum = 0;
        // walk up to nearest non-data
        let i = idx - w;
        while (i > 0) {
            if (!(cells[i].is_data)) {
            	sum = parseInt(cells[i].display[0]);
            	firstCellRow = cells[i+w].row;
            	firstCellCol = cells[i+w].col;
                break;
            } else {
                strip.cells.push(cells[i].idx);
            }
            i = i - w;
        }

        // since this cell is a data cell, add it;
        strip.cells.push(idx);

        // walk down to nearest non-data
        i = idx + w;
        while (i < h * w) {
            if (!(cells[i].is_data)) {
                break;
            } else {
                strip.cells.push(cells[i].idx);
            }
            i += w;
        }
        strip.idx = firstCellRow + '_' + firstCellCol + '_v';
        strip.sum = sum;
        strips[strip.idx] = strip;
        strip.cells.forEach(c => {
        	cells[c].strips[0] = strip.idx;
        	cells[c].known = false;
        })
        strip.unknown = strip.cells.length;

        strip = {cells:[], changed: true};

        // walk left to nearest non-data
        i = idx - 1;
        while (i % w !== w - 1) { // walk until you have wrapped
            if (!(cells[i].is_data)) {
            	sum = parseInt(cells[i].display[1]);
            	firstCellRow = cells[i+1].row;
            	firstCellCol = cells[i+1].col;
                break;
            } else {
                strip.cells.push(cells[i].idx);
            }
            i = i - 1;
        }

        strip.cells.push(idx);
 
        // walk right to nearest non-data
        i = idx + 1;
        while (i % w) {
            if (!(cells[i].is_data)) {
                break;
            } else {
                strip.cells.push(cells[i].idx);
            }
            i += 1;
        }

        strip.idx = firstCellRow + '_' + firstCellCol + '_h';
        strip.sum = sum;
        strips[strip.idx] = strip;
        strip.cells.forEach(c => {
        	cells[c].strips[1] = strip.idx;
        	cells[c].known = false;
        });
        strip.unknown = strip.cells.length;

        return { cells, strips };
	},

	buildStripWeb(i, cells, strips, web = [], used = [], queue = []) {
		if (!web.length) {
			if (i < 0) {
				cells.some(c => {
					if (c.is_data) {
						i = c.row * this.width + c.col;
						return true;
					}
					return false;
				});
			}
			cells[i].strips.forEach(stripIdx => {
				web.push(stripIdx);
				strips[stripIdx].changed = true;
				queue.push(stripIdx);
			});

			return this.buildStripWeb(i, cells, strips, web, used, queue);
		}

		let stripIdx = queue.shift();
		let tempQueue = [];
		used.push(stripIdx);
		strips[stripIdx].cells.forEach(cellIdx => {
			cells[cellIdx].strips.forEach(sidx => {
				if (web.indexOf(sidx) < 0) {
					web.push(sidx);
					strips[sidx].changed = true;
				}
				if (queue.indexOf(sidx) < 0 && used.indexOf(sidx) < 0 && tempQueue.indexOf(sidx) < 0) {
					tempQueue.push(sidx);
				}
			});
		});

		tempQueue.forEach(q => {
			queue.push(q);
		});
		// queue.sort((x,y)=>{return x.unknown - y.unknown});

		return queue.length ? this.buildStripWeb(i, cells, strips, web, used, queue) : web;
	},

	intersection(arrays) {
		if (!(arrays instanceof Array)) {
			return null;
		}
		if (!(arrays[0] instanceof Array)) {
			return arrays;
		}
		if (arrays.length === 1) {
			return arrays[0];
		}

		return this.intersect(arrays.pop(), this.intersection(arrays));
	},

	union(a, set = []) {
		if (a instanceof Array) {
            a.forEach(s => {
                set = this.union(s, set); 
            });
        } else {
            if (set.indexOf(a) < 0) {
                set.push(a);
            }
        }

        return set.sort();
	},	

	intersect(a, b) {
	    return a.filter(e => {
	        return b.indexOf(e) > -1;
	    });
	},

	diff(a, b) {
		return a.filter(e => {
			return b.indexOf(e) < 0;
		})
	},

	coords(cell) {
		return '(' + cell.row + ',' + cell.col + ')';
	}
}