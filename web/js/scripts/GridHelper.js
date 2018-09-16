// export const getChoices = (cells) => {
//     var url = "http://kak.uro/app_dev.php/api/build/get-choices";
//     var data = {cells: JSON.stringify(cells)};
//     return $.post(
//         url,
//         data,
//         function(resp) {
//             cells = resp.cells;
//         },
//         'json'
//     );
// }

export const GridHelper = {
    message: 'test',
ctr: 0,
    peers(idx, cells, h, w) {
        var strips = this.strips(idx, cells, h, w);
        var p = [];
        strips.forEach((strip) => {
            strip.forEach((cell) => {
                p.push(cell);
            });
        });

        return p;
    },

    allStrips(cells, h, w) {
        var nbrStrips, strips = [], stripIdxs = [];
        cells.forEach(cell => {
            if (cell.row === 0 || cell.col === 0) {
                return;
            }
            nbrStrips = this.strips(cell.idx, cells, h, w);
            nbrStrips.forEach(strip => {
                // strip idx is row_col with __ between dudes like 1_1__1_2 or row_col for first and orientation like 1_1_h
                strip.idx = this.stripIndex(strip);
                if (stripIdxs.indexOf(strip.idx) < 0) {
                    stripIdxs.push(strip.idx);
                    strips.push(strip);
                }
            });
        });

        return strips;
    },

    stripIndex(strip) {
        var isHorizontal = true, minRow = null, minCol = null, prevRow = null;
        strip.forEach(cell => {
            if (minRow === null || cell.row < minRow) {
                minRow = cell.row;
            }

            if (minCol === null || cell.col < minCol) {
                minCol = cell.col;
            }

            if (prevRow !== null && cell.row != prevRow) { 
                isHorizontal = false;
            }

            prevRow = cell.row;
        });

        var orientation = isHorizontal ? '_h' : '_v';
        var stripStart =  minRow + '_' + minCol ;

        return stripStart + orientation;
    },

    strips: (idx, cells, h, w) => {
        var isDataCell = cells[idx].is_data;
        var nbrs = [];

        // vertical
        // walk up to nearest non-data
        var strip = [];
        var i = idx - w;
        while (i > 0) {
            if (!(cells[i].is_data)) {
                var topNonDataNbrIdx = i;
                break;
            } else {
                strip.push(cells[i]);
            }
            i = i - w;
        }

        // walk down to nearest non-data
        if (!isDataCell) {
            nbrs.push(strip);
            strip = [];
        } else {
            strip.push(cells[idx]);
        }

        i = idx + w;
        while (i < h * w) {
            if (!(cells[i].is_data)) {
                break;
            } else {
                strip.push(cells[i]);
            }
            i += w;
        }
        nbrs.push(strip);

        // walk left to nearest non-data
        i = idx - 1;
        strip = [];
        while (i % w !== w - 1) { // walk until you have wrapped
            if (!(cells[i].is_data)) {
                var leftNonDataNbrIdx = i;
                break;
            } else {
                strip.push(cells[i]);
            }
            i = i - 1;
        }

        if (!isDataCell) {
            nbrs.push(strip);
            strip = [];
        } else {
            strip.push(cells[idx]);
        }
     
        // walk right to nearest non-data
        i = idx + 1;
        while (i % w) {
            if (!(cells[i].is_data)) {
                break;
            } else {
                strip.push(cells[i]);
            }
            i += 1;
        }
        nbrs.push(strip);

        return nbrs;
    },

    adjustAllLabels(cells, h, w) {
        cells.forEach(cell => {
            if (cell.is_data) {
                cells = this.adjustLabels(cell.idx, cells, h, w);
            }
        });

        return cells;
    },

    adjustLabels: (idx, cells, h, w) => {
        var isDataCell = cells[idx].is_data;
        // walk up to nearest non-data
        var i = idx - w;
        var nbrs = [];
        while (i > 0) {
            if (!(cells[i].is_data)) {
                var topNonDataNbrIdx = i;
                break;
            } else {
                nbrs.push(cells[i]);
            }
            i = i - w;
        }

        var stripSum = 0;
        var sumIsValid = true;
        if (nbrs.length > 0) {
            nbrs.forEach((c) => {
                if (c.choices.length !== 1) {
                    sumIsValid = false;
                    stripSum = 0;
                }
                if (sumIsValid) {
                    stripSum += c.choices[0];
                }
            });
        }

        if (!isDataCell) {
            if (sumIsValid) {
                cells[topNonDataNbrIdx].display[0] = stripSum;
                stripSum = 0;
            } else {
                sumIsValid = true;
            }
            topNonDataNbrIdx = idx;
        }

        if (sumIsValid) {
            if (isDataCell) {
                stripSum += cells[idx].choices[0];
            }
            // walk down to nearest non-data
            i = idx + w;
            nbrs = [];
            while (i < h * w) {
                if (!(cells[i].is_data)) {
                    break;
                } else {
                    nbrs.push(cells[i]);
                }
                i += w;
            }

            if (nbrs.length > 0) {
                nbrs.forEach((c) => {
                    if (c.choices.length !== 1) {
                        sumIsValid = false;
                        stripSum = 0;
                    }
                    if (sumIsValid) {
                        stripSum += c.choices[0];
                    }
                });
            }

            if (sumIsValid) {
                cells[topNonDataNbrIdx].display[0] = stripSum;
            }
        }

        // horizontal

        // walk left to nearest non-data
        i = idx - 1;
        nbrs = [];
        while (i % w !== w - 1) { // walk until you have wrapped
            if (!(cells[i].is_data)) {
                var leftNonDataNbrIdx = i;
                break;
            } else {
                nbrs.push(cells[i]);
            }
            i = i - 1;
        }

        stripSum = 0;
        sumIsValid = true;
        if (nbrs.length > 0) {
            nbrs.forEach((c) => {
                if (c.choices.length !== 1) {
                    sumIsValid = false;
                    stripSum = 0;
                }
                if (sumIsValid) {
                    stripSum += c.choices[0];
                }
            });
        }

        if (!isDataCell) {
            if (sumIsValid) {
                cells[leftNonDataNbrIdx].display[1] = stripSum;
                stripSum = 0;
            } else {
                sumIsValid = true;
            }
            leftNonDataNbrIdx = idx;
        }

        if (sumIsValid) {
            if (isDataCell) {
                stripSum += cells[idx].choices[0];
            }
            // walk right to nearest non-data
            i = idx + 1;
            nbrs = [];
            while (i % w) {
                if (!(cells[i].is_data)) {
                    break;
                } else {
                    nbrs.push(cells[i]);
                }
                i += 1;
            }

            if (nbrs.length > 0) {
                nbrs.forEach((c) => {
                    if (c.choices.length !== 1) {
                        sumIsValid = false;
                        stripSum = 0;
                    }
                    if (sumIsValid) {
                        stripSum += c.choices[0];
                    }
                });
            }

            if (sumIsValid) {
                cells[leftNonDataNbrIdx].display[1] = stripSum;
            }
        }

        return cells;
    },

    valAllowed(val, idx, cells, h, w) {
        var isDataCell = cells[idx].is_data;
        var allowed = true;
        // if (parseInt(val)) {
        //     allowed = false;
        //     cells[idx].choices.forEach(v => {
        //         if (v === val) {
        //             allowed = true;
        //             return;
        //         }
        //     });
        // }

        // if (isDataCell && val === 'x') {
        //     var strips = this.strips(idx, cells, h, w);
        //     strips.forEach((strip) => {
        //         if (strip.length === 1) {
        //             allowed = false;
        //         }
        //     });
        // }

        return allowed;
    },

    getCellArray: (h, w) => {
        var c = [];
        for (var i = 0; i < h * w; i++) {
            if (i < w || !(i % w)) {
                c.push({choices: [], is_editable: false, is_data: false, idx: i});
            } else {
                c.push({choices: [1,2,3,4,5,6,7,8,9], is_editable: true, is_data: true, idx: i});
            }
        }

        return c;
    },

    reduce(idx, cells, h, w) {
        var p = this.peers(idx, cells, h, w);
        p.forEach((cell) => {
            var i = cell.row * w + cell.col;
            cells[i] = this.reduceCell(cell, cells, h, w);
        });

        return cells;
    },

    reduceCell(cell, cells, h, w) {
        if (cell.is_data && cell.choices.length !== 1) {
            var peers = this.peers(cell.row * w + cell.col, cells, h, w);
            var available = [1,2,3,4,5,6,7,8,9];
            peers.forEach((peer) => {
                if (peer.choices.length === 1) {
                    var choice = peer.choices[0];
                    available.forEach((itm, k) => {
                        if (itm === choice) {
                            available.splice(k, 1);
                        }
                    });
                }
            });

            cell.choices = available;
        }

        return cell;
    },

    validGrid(cells, h, w) {
console.log('vg');
        if (!this.validFrame(cells, h, w)) {
console.log('frame not good');
            return false;
        }
        if (!this.validValues(cells, h, w)) {
console.log('values not good');
            return false;
        }

        return true;
    },

    validFrame(cells, h, w) {
        var strips = [], allowed = true;
        cells.some(cell => {
            if (cell.row && cell.col && !cell.is_data) {
                strips = this.strips(cell.idx, cells, h, w);
                strips.forEach((strip) => {
                    if (strip.length === 1) {
                        this.message = '(' + cell.row + ', ' + cell.col + ') causes an invalid strip';
                        allowed = false;
                        return true;
                    }
                });
            }
        });

        return allowed;
    },

    validValues(cells, h, w) {
        var strips = [], allowed = true;
        cells.some(cell => {
            if (cell.is_data) {
                if (cell.choices.length !== 1) {
                    this.message = '(' + cell.row + ', ' + cell.col + ') has ' +  cell.choices.length + ' choices';
                    allowed = false;
                    return true;
                }
            }
        });

        if (!allowed) {
            return false;
        }

        strips = this.allStrips(cells, h, w);
        strips.some(strip => {
            if (!this.stripContainsUniqueValues(strip)) {
                this.message = strip.idx + ' has a repeated value';
                allowed = false;
                return true;
            }
        });

        return allowed;
    },

    stripContainsUniqueValues(strip) {
        var unique = true;
        var vals = [];
        strip.some(cell => {
            if (vals.indexOf(cell.choices[0]) > -1) {
                unique = false;
                return false;
            }
            vals.push(cell.choices[0]);
        });

        return unique;
    },

    checkSwap(idx, cells, h, w) {
        var cell = cells[idx];
        var cellGroup = []
        if (!cell.is_data) {
            return true;
        }
        var strips = this.strips(idx, cells, h, w);
        var commonValuedCellPairs = this.interesectByValue(strips);
console.log('cvcp len: ' + commonValuedCellPairs.length);
        if (commonValuedCellPairs.length < 1) {
            return true;
        }

commonValuedCellPairs.forEach(pair => {
    console.log(pair[0].idx, pair[1].idx);
});
        commonValuedCellPairs.forEach(pair => {
            cellGroup = this.findConnectedStripsMutuallyContaining(cell.choices[0], pair, [cell], cells, h, w);
        });
console.log('cell gp:');
cellGroup.forEach(cel => {
    console.log(cel.idx);
});
if (cellGroup.length > 2 && !(cellGroup.length % 2)) {
    console.log('this would fail');

}
    },

    interesectByValue(strips) {
        var cells = [];
        if (strips[0].length < 1 || strips[1].length < 1) {
            return [];
        }

        strips[0].forEach(cell => {
            if (cell.choices.length > 1) {
                return;
            }
            strips[1].forEach(cell1 => {
                if (cell1.choices.length > 1) {
                    return;
                }

                if (cell1.choices[0] === cell.choices[0]) {
                    cells.push([cell, cell1]);
                }
            });
        });

        return cells;
    },

    findConnectedStripsMutuallyContaining(val, pair, cellGroup, cells, h, w) {
if (this.ctr++ > 100) {
    console.log('runaway recurse');
    return cellGroup;
}
        var pairForRecurse, strips, found = false, cellInGroup = false;
        pair.forEach(cell => {
            // move on if cell already in group
            cellGroup.some(c => {
                if (c.idx === cell.idx) {
                    cellInGroup = true;
                    return true;
                }
            });
            if (cellInGroup) {
                cellInGroup = false;
                return;
            }

            pairForRecurse = [];
            strips = this.strips(cell.idx, cells, h, w);
            strips.forEach(strip => {
                strip.some(c => {
                    found = false;
                    if (c.choices.count === 1 && val === c.choices[0]) {
                        found = true;
                        pairForRecurse.push(c);
                        return true;
                    }
                });
                if (found) {
                    cellGroup.push(cell);
                    cellGroup = this.findConnectedStripsMutuallyContaining(cell.choices[0], pairForRecurse, cellGroup, cells, h, w);
                }
            });
        });

        return cellGroup;
    },

    saveGame(name, cells, h, w) {
        console.log('writing...');
        return true;
    },
}