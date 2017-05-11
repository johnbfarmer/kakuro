var Grid = React.createClass({
    getInitialState: function() {
        return { cells: [], height: 0, width: 0, active_row: 1, active_col: 2 };
    },
    componentDidMount: function() {
        this.getGrid();
    },
    getGrid: function() {
        return $.getJSON(
            "http://kak.uro/app_dev.php/api/grid/" + this.props.filename
        ).then(data => {
            this.setState({ cells: data.cells, height: data.height, width: data.width });
        });
    },
    setActive: function(row, col) {
        this.setState({active_row: row, active_col: col});
    },
    handleChangedCell: function(row, col, val) {
        var idx = row * this.state.width + col;
        var cells = this.state.cells;
        var cell = cells[idx];
        // console.log('ch cell', cell);
        cells[idx].choices = val;
        this.setState({cells: cells});
    },
    render: function() {
        var cells = this.state.cells.map(function(cell, index) {
            var col = index % this.state.width;
            var row = Math.floor(index / this.state.width);
            var active = row === this.state.active_row && col === this.state.active_col;
            return <Cell cell={cell} key={index} row={row} col={col} active={active} onClick={() => this.setActive(row, col)} onChange={this.handleChangedCell} />;
        }, this);
        return (
            <div className="kakuro-grid">
               {cells}
            </div>
        );
    }
});

var Cell = React.createClass({
    getInitialState: function() {
        var cell = this.props.cell;
        var editable = cell.is_data;
        var display = cell.choices.join('');
        if (!editable) {
            var leftText = cell.display[0] ? cell.display[0].toString() : "";
            var rightText = cell.display[1] ? cell.display[1].toString() : "";
            if (leftText.length > 0 || rightText.length > 0) {
                display = leftText + "\\" + rightText;
            }
        }
        return { 
            display: display, 
            choices: cell.choices,
            editable: editable, 
            active: this.props.active, 
            row: this.props.row,
            col: this.props.col,
            cursorPos: 0
        };
    },
    componentDidUpdate: function() {
        // console.log(this.state.row, this.state.col, ' updated');
        this.state.cell = this.props.cell;
        this.state.active = this.props.active;
        this.state.choices = this.props.cell.choices;
        this.state.display = this.state.choices.join('');
        if (this.state.active) {
            if (this.choiceInput) {
                console.log(this.state.row, this.state.col)
                this.choiceInput.focus();
                this.state.cursorPos = 0;
            }
        }
    },
    getClasses: function() {
        var classes = "kakuro-cell";
        if (!this.state.editable) {
            classes = classes + " blnk";
        }
        if (this.props.active) {
            classes = classes + " red";
        }
        if (this.props.col === 0) {
            classes = classes + " clr";
        }
        return classes;
    },
    setActive: function() {
        if (this.state.editable) {
            this.props.onClick();
        }
    },
    handleKeyDown: function(event) {
        if (!this.state.active) {
            console.log('hkd',this);
            return;
        }
        var choices = this.state.choices;
        var evtCode = event.keyCode;
        if (evtCode === 39) {
            console.log(event.key);
            this.state.cursorPos++;
        }
        if (evtCode === 37) {
            console.log(event.key);
            this.state.cursorPos--;
        }
        if (evtCode === 8) {
            console.log(event.key);
            var idx = this.state.cursorPos - 1;
            choices.splice(idx, 1);
            this.setState({choices: choices, display: choices.join('')});
        }
        var val = parseInt(event.key);
        console.log(event.keyCode);
        if ($.inArray(val, [1,2,3,4,5,6,7,8,9]) > 0 && $.inArray(val, choices) < 0) {
            choices.push(val);
            choices.sort(); 
            this.setState({choices: choices, display: choices.join('')});
        }
    },
    handleChange: function(event) {
        // console.log(909, event.target.value);
        this.props.onChange(this.state.row, this.state.col, this.state.choices);
    },
    render: function() {
        // console.log(this.props.row, this.props.col, this.props.active);
        if (this.props.active) {
            // console.log('actv',this.props.cell);
            return (
                <div className={this.getClasses()}>
                    <input type="text" onKeyDown={this.handleKeyDown} value={this.state.display} onChange={this.handleChange} ref={(input) => { this.choiceInput = input; }} />
                </div>
            );
        }
        return (
            <div className={this.getClasses()} onClick={() => this.setActive()}>
                {this.state.display}
            </div>
        );
    }
});

ReactDOM.render(<Grid filename={filename}/>, document.getElementById("content"));
