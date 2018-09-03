import React from 'react';
import { ButtonGroup, Button, Glyphicon } from 'react-bootstrap';

export default class KakuroControls extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            savedGameName: '',
            grids: [],
            createMode: false,
            height: 8,
            width: 8,
        };

        this.loadVals(props);

        this.updateSavedGameName = this.updateSavedGameName.bind(this);
        this.changeGrid = this.changeGrid.bind(this);
        this.save = this.save.bind(this);
        this.updateNewAttributes = this.updateNewAttributes.bind(this);
        this.newGrid = this.newGrid.bind(this);
    }

    componentDidMount() {}

    componentWillUpdate(props) {
        this.loadVals(props);
    }

    loadVals(props) {
        this.state.savedGameName = props.savedGameName;
        this.state.grids = this.processDropdownOptions(props.grids);
        this.state.createMode = props.createMode || false;
    }

    updateSavedGameName(e) {
        var val = e.target.value;
        this.setState({savedGameName: val});
    }

    save() {
        this.props.save(this.state.savedGameName);
    }

    processDropdownOptions(data) {
        var arr = [];
        arr.push(<option key="topChoice" value={0}>--select--</option>);
        for (var i = 0; i < data.length; i++) {
            var option = data[i];
            var label = 'label' in option ? option.label : option.name;
            var val = option.name;
            arr.push(<option key={i} value={val}>{label}</option>);
          }

        return arr;
    }

    changeGrid(e) {
        this.props.getGrid(e.target.value);
    }

    updateNewAttributes(e) {
        var val = e.target.value;
        var ds = e.target.dataset;
        var k = ds.newattr;
        if (k === 'height' || k === 'width') {
            val = parseInt(val);
            if (isNaN(val)) {return}
        }
        var s = this.state;
        s[k] = val;
        this.setState(s);
    }

    newGrid(e) {
        console.log('new grid height '+this.state.height+' width '+this.state.width);
        this.props.newGrid(this.state.height, this.state.width);
    }

    getNewGridAttributes() {
        if (!this.state.createMode) {
            return '';
        }

        return (
            <div className="row">
                height: <input value={this.state.height} data-newattr="height" onChange={this.updateNewAttributes} />
                width: <input value={this.state.width} data-newattr="width" onChange={this.updateNewAttributes} />
                    <ButtonGroup>
                        <Button onClick={this.newGrid} title="save">
                            <Glyphicon glyph="floppy-disk" />
                        </Button>
                    </ButtonGroup>
            </div>
        );
    }

    render() {
        var newGridAttributes = this.getNewGridAttributes();
        return (
            <div>
                <div className="row">
                    <select onChange={this.changeGrid} value={this.props.selectedGrid}>
                        {this.state.grids}
                    </select>
                </div>
                {newGridAttributes}
                <div className="row">
                    <input value={this.state.savedGameName} onChange={this.updateSavedGameName} />
                    <ButtonGroup>
                        <Button onClick={this.save} title="save">
                            <Glyphicon glyph="floppy-disk" />
                        </Button>
                    </ButtonGroup>
                </div>
            </div>
        );
    }
}

